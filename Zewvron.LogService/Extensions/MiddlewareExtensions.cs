using Microsoft.AspNetCore.Builder;
using Zewvron.LogService.Middleware;

namespace Zewvron.LogService.Extensions
{
    public static class MiddlewareExtensions
    {
        public static void ConfigureExceptionHandler(this IApplicationBuilder app)
        {
           
        }

        public static void ConfigureCustomExceptionMiddleware(this IApplicationBuilder app)
        {
            app.UseMiddleware<ExceptionMiddleware>();
            
        }
    }
}
