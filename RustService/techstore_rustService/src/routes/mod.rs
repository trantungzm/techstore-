use axum::Router;
use axum::http::HeaderValue;
use tower_http::{
    cors::{AllowOrigin, Any, CorsLayer},
    trace::TraceLayer,
};

use crate::state::AppState;

pub mod product_compare_routes;
pub mod recommendation_routes;

pub fn app_router(state: AppState, cors_origins: &[String]) -> Router {
    let api_router = Router::new()
        .merge(product_compare_routes::routes())
        .merge(recommendation_routes::routes());

    let origins = cors_origins
        .iter()
        .filter_map(|origin| origin.parse::<HeaderValue>().ok())
        .collect::<Vec<_>>();

    let cors = CorsLayer::new()
        .allow_origin(AllowOrigin::list(origins))
        .allow_methods(Any)
        .allow_headers(Any);

    Router::new()
        .nest("/api/rust", api_router)
        .with_state(state)
        .layer(cors)
        .layer(TraceLayer::new_for_http())
}
