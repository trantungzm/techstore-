using Ocelot.DependencyInjection;
using Ocelot.Middleware;
using Microsoft.AspNetCore.StaticFiles;
using Microsoft.Extensions.FileProviders;

var builder = WebApplication.CreateBuilder(args);

builder.Logging.ClearProviders();
builder.Logging.AddConsole();
builder.Logging.AddDebug();

// Add Ocelot configuration
builder.Configuration.AddJsonFile("ocelot.json", optional: false, reloadOnChange: true);

builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen();

// CORS
builder.Services.AddCors(options =>
{
    options.AddPolicy("AllowAll", policy =>
    {
        policy.AllowAnyOrigin()
              .AllowAnyMethod()
              .AllowAnyHeader();
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

app.UseCors("AllowAll");

var contentTypeProvider = new FileExtensionContentTypeProvider();
contentTypeProvider.Mappings[".webp"] = "image/webp";
contentTypeProvider.Mappings[".avif"] = "image/avif";

var webRootPath = Path.Combine(app.Environment.ContentRootPath, "wwwroot");
if (Directory.Exists(webRootPath))
{
    app.UseDefaultFiles();
    app.UseStaticFiles(new StaticFileOptions
    {
        FileProvider = new PhysicalFileProvider(webRootPath),
        ContentTypeProvider = contentTypeProvider,
    });

    var uploadsRootPath = builder.Configuration["Uploads:RootPath"];
    if (string.IsNullOrWhiteSpace(uploadsRootPath))
    {
        uploadsRootPath = Path.GetFullPath(Path.Combine(app.Environment.ContentRootPath, "..", "BaseCore.APIService", "wwwroot", "uploads"));
    }

    if (Directory.Exists(uploadsRootPath))
    {
        app.UseStaticFiles(new StaticFileOptions
        {
            FileProvider = new PhysicalFileProvider(uploadsRootPath),
            RequestPath = "/uploads",
            ContentTypeProvider = contentTypeProvider,
        });
    }

    var imageShopRootPath = builder.Configuration["ImageShop:RootPath"];
    if (string.IsNullOrWhiteSpace(imageShopRootPath))
    {
        imageShopRootPath = Path.GetFullPath(Path.Combine(app.Environment.ContentRootPath, "..", "Image_Shop"));
    }

    if (Directory.Exists(imageShopRootPath))
    {
        app.UseStaticFiles(new StaticFileOptions
        {
            FileProvider = new PhysicalFileProvider(imageShopRootPath),
            RequestPath = "/image-shop",
            ContentTypeProvider = contentTypeProvider,
        });
    }

    var pictureSpRootPath = builder.Configuration["PictureSP:RootPath"];
    if (string.IsNullOrWhiteSpace(pictureSpRootPath))
    {
        pictureSpRootPath = Path.GetFullPath(Path.Combine(app.Environment.ContentRootPath, "..", "Picture SP"));
    }

    if (Directory.Exists(pictureSpRootPath))
    {
        app.UseStaticFiles(new StaticFileOptions
        {
            FileProvider = new PhysicalFileProvider(pictureSpRootPath),
            RequestPath = "/picture-sp",
            ContentTypeProvider = contentTypeProvider,
        });
    }

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
 BaseCore API Gateway
------------------------------------------------------------
 Gateway:        http://localhost:5000
 APIService:     http://localhost:5001
 AuthService:    http://localhost:5002
============================================================
");

app.Run();
