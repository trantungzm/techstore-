using Ocelot.DependencyInjection;
using Ocelot.Middleware;
using Microsoft.Extensions.FileProviders;

var builder = WebApplication.CreateBuilder(args);

builder.Logging.ClearProviders();
builder.Logging.AddConsole();
builder.Logging.AddDebug();

// Add Ocelot configuration
builder.Configuration.AddJsonFile("ocelot.json", optional: false, reloadOnChange: true);

builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen();

// CORS — restrict to a configured origin whitelist. In dev, fall back to the Vite dev server
// origin if Cors:WithOrigin isn't set; outside dev, an unset/empty value fails startup instead
// of silently allowing every origin (previously AllowAnyOrigin() unconditionally, even in prod).
builder.Services.AddCors(options =>
{
    options.AddPolicy("AllowConfiguredOrigins", policy =>
    {
        var originsConfig = builder.Configuration["Cors:WithOrigin"];
        if (builder.Environment.IsDevelopment())
        {
            var devOrigins = string.IsNullOrWhiteSpace(originsConfig)
                ? new[] { "http://localhost:3000" }
                : originsConfig.Split(',', StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries);
            policy.WithOrigins(devOrigins).AllowAnyMethod().AllowAnyHeader();
            return;
        }

        if (string.IsNullOrWhiteSpace(originsConfig))
        {
            throw new InvalidOperationException("Cors:WithOrigin chưa được cấu hình cho môi trường production.");
        }

        var origins = originsConfig.Split(',', StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries);
        policy.WithOrigins(origins).AllowAnyMethod().AllowAnyHeader();
    });
});

// Add Ocelot
builder.Services.AddOcelot();

var app = builder.Build();

if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI();
}

app.UseCors("AllowConfiguredOrigins");

var webRootPath = Path.Combine(app.Environment.ContentRootPath, "wwwroot");
if (Directory.Exists(webRootPath))
{
    app.UseDefaultFiles();
    app.UseStaticFiles(new StaticFileOptions
    {
        FileProvider = new PhysicalFileProvider(webRootPath)
    });

    app.Use(async (context, next) =>
    {
        var path = context.Request.Path;
        var isApiRequest = path.StartsWithSegments("/api");
        var isSwaggerRequest = path.StartsWithSegments("/swagger");
        var hasFileExtension = Path.HasExtension(path.Value);

        if (!isApiRequest && !isSwaggerRequest && !hasFileExtension)
        {
            context.Response.ContentType = "text/html; charset=utf-8";
            await context.Response.SendFileAsync(Path.Combine(webRootPath, "index.html"));
            return;
        }

        await next();
    });
}

// Ocelot must be last
await app.UseOcelot();

Console.WriteLine(@"
============================================================
 Zewvron API Gateway
------------------------------------------------------------
 Gateway:        http://localhost:5000
 APIService:     http://localhost:5001
 AuthService:    http://localhost:5002
============================================================
");

app.Run();
