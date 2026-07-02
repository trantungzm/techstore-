use axum::{routing::get, Json, Router};
use serde::Serialize;
use std::net::SocketAddr;

#[derive(Serialize)]
struct HealthResponse {
    service: &'static str,
    status: &'static str,
    modules: [&'static str; 2],
}

async fn health() -> Json<HealthResponse> {
    Json(HealthResponse {
        service: "tech-rust-backend-service",
        status: "ok",
        modules: ["recommendations", "notifications-worker"],
    })
}

#[tokio::main]
async fn main() {
    let app = Router::new().route("/health", get(health));
    let addr = SocketAddr::from(([127, 0, 0, 1], 5004));

    println!("tech-rust-backend-service scaffold listening on http://{}", addr);

    let listener = tokio::net::TcpListener::bind(addr)
        .await
        .expect("bind listener");

    axum::serve(listener, app).await.expect("serve app");
}
