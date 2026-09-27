use std::env;

use tiberius::{Config, EncryptionLevel};

use crate::error::{ApiError, ApiResult};

#[derive(Clone)]
pub struct AppConfig {
    pub bind_addr: String,
    pub database_url: String,
    pub db_pool_size: usize,
    pub cors_origins: Vec<String>,
}

impl AppConfig {
    pub fn from_env() -> ApiResult<Self> {
        // Default to loopback-only — this service has no auth layer of its own (see
        // src/routes/mod.rs), so binding 0.0.0.0 by default would expose it directly on any
        // reachable network interface. Only bind 0.0.0.0 deliberately, e.g. inside an
        // isolated container network where the host firewall/security group is the boundary.
        let bind_addr =
            env::var("TECHSTORE_RUST_BIND").unwrap_or_else(|_| "127.0.0.1:7001".to_string());

        let database_url = env::var("TECHSTORE_RUST_DATABASE_URL").unwrap_or_else(|_| {
            "Server=LUONG-CONG;Database=techstore;Integrated Security=true;Encrypt=false;TrustServerCertificate=true"
                .to_string()
        });
        let db_pool_size = env::var("TECHSTORE_RUST_DB_POOL_SIZE")
            .ok()
            .and_then(|value| value.parse::<usize>().ok())
            .unwrap_or(4)
            .clamp(1, 64);

        let cors_origins = Self::resolve_cors_origins()?;

        Ok(Self {
            bind_addr,
            database_url,
            db_pool_size,
            cors_origins,
        })
    }

    // Whitelist comes from TECHSTORE_RUST_CORS_ORIGINS (comma-separated). Debug builds (plain
    // `cargo run`/`cargo build`) fall back to the local frontend/gateway origins for convenience;
    // release builds refuse to start with CORS wide open — no permissive fallback, ever.
    fn resolve_cors_origins() -> ApiResult<Vec<String>> {
        match env::var("TECHSTORE_RUST_CORS_ORIGINS") {
            Ok(raw) => {
                let origins = raw
                    .split(',')
                    .map(str::trim)
                    .filter(|value| !value.is_empty())
                    .map(str::to_string)
                    .collect::<Vec<_>>();
                if origins.is_empty() {
                    return Err(ApiError::config(
                        "TECHSTORE_RUST_CORS_ORIGINS is set but contains no origins".to_string(),
                    ));
                }
                Ok(origins)
            }
            Err(_) if cfg!(debug_assertions) => Ok(vec![
                "http://localhost:3000".to_string(),
                "http://localhost:5000".to_string(),
            ]),
            Err(_) => Err(ApiError::config(
                "TECHSTORE_RUST_CORS_ORIGINS must be set (comma-separated origin whitelist) in release builds — refusing to start with CORS wide open".to_string(),
            )),
        }
    }

    pub fn db_config(&self) -> ApiResult<Config> {
        let normalized = normalize_ado_connection_string(&self.database_url);
        let mut config = Config::from_ado_string(&normalized).map_err(|err| {
            ApiError::config(format!("Invalid SQL Server connection string: {err}"))
        })?;
        if has_flag(&normalized, "encrypt", "false") {
            config.encryption(EncryptionLevel::NotSupported);
        }
        if has_flag(&normalized, "trustservercertificate", "true") {
            config.trust_cert();
        }
        config.application_name("TechStoreRustService");
        Ok(config)
    }
}

fn normalize_ado_connection_string(raw: &str) -> String {
    raw.split(';')
        .filter(|part| !part.trim().is_empty())
        .map(|part| {
            let trimmed = part.trim();
            let Some((key, value)) = trimmed.split_once('=') else {
                return trimmed.to_string();
            };

            let normalized_key = key.trim().to_ascii_lowercase().replace(' ', "");
            if normalized_key == "trusted_connection" {
                format!("Integrated Security={}", value.trim())
            } else {
                format!("{}={}", key.trim(), value.trim())
            }
        })
        .collect::<Vec<_>>()
        .join(";")
}

fn has_flag(raw: &str, expected_key: &str, expected_value: &str) -> bool {
    raw.split(';').any(|part| {
        let Some((key, value)) = part.trim().split_once('=') else {
            return false;
        };
        key.trim().to_ascii_lowercase().replace(' ', "") == expected_key
            && value.trim().eq_ignore_ascii_case(expected_value)
    })
}
