using Zewvron.APIService.Extensions;
using Zewvron.APIService.Hubs;
using Zewvron.APIService.Validators;
using Zewvron.Repository;
using FluentValidation;
using FluentValidation.AspNetCore;
using Microsoft.AspNetCore.DataProtection;
using Microsoft.EntityFrameworkCore;
using System.Text.Json;
using System.Text.Json.Serialization;

var builder = WebApplication.CreateBuilder(args);

// JWT signing secret must never be hardcoded in appsettings.json (it was, and got committed to git
// history — see security audit). Read it from the JWT_SECRET env var (or dotnet user-secrets in dev),
// overriding whatever appsettings has. If neither is set, AddJwtAuth's Jwt:SecretKey null-check fails
// startup loudly instead of running with no/empty signing key.
var jwtSecretFromEnv = Environment.GetEnvironmentVariable("JWT_SECRET");
if (!string.IsNullOrWhiteSpace(jwtSecretFromEnv))
{
    builder.Configuration["Jwt:SecretKey"] = jwtSecretFromEnv;
}

builder.Logging.ClearProviders();
builder.Logging.AddConsole();
builder.Logging.AddDebug();

var dataProtectionKeysPath = Path.Combine(builder.Environment.ContentRootPath, ".data-protection-keys");
Directory.CreateDirectory(dataProtectionKeysPath);
builder.Services.AddDataProtection()
    .SetApplicationName("Zewvron.APIService")
    .PersistKeysToFileSystem(new DirectoryInfo(dataProtectionKeysPath));

// Add services to the container
builder.Services.AddControllers()
    .AddJsonOptions(options =>
    {
        options.JsonSerializerOptions.PropertyNamingPolicy = JsonNamingPolicy.CamelCase;
        options.JsonSerializerOptions.PropertyNameCaseInsensitive = true;
        options.JsonSerializerOptions.ReferenceHandler = ReferenceHandler.IgnoreCycles;
    });
builder.Services.AddFluentValidationAutoValidation();
builder.Services.AddValidatorsFromAssemblyContaining<CategoryUpsertDtoValidator>();
builder.Services.AddSignalR();

builder.Services.AddSwaggerDocs();
builder.Services.AddCorsPolicy(builder.Configuration, builder.Environment);
builder.Services.AddPersistence(builder.Configuration);
builder.Services.AddDomainServices();
builder.Services.AddJwtAuth(builder.Configuration);

var app = builder.Build();

app.UseApiExceptionHandler();

// Runtime must read/write only the configured SQL Server zewvron database.
// Apply schema/data changes explicitly instead of seeding automatically on startup.
var autoMigrateOnStartup = builder.Configuration.GetValue("Database:AutoMigrateOnStartup", false);
if (autoMigrateOnStartup)
{
    using var scope = app.Services.CreateScope();
    var db = scope.ServiceProvider.GetRequiredService<AppDbContext>();

    // Create database and apply migrations
    try
    {
        db.Database.Migrate();
        await db.SeedDataAsync();

        Console.WriteLine("Database migrated and seeded successfully");
    }
    catch (Exception ex)
    {
        Console.Error.WriteLine("Database migration/seed failed. Check DefaultConnection.");
        Console.Error.WriteLine(ex);
    }
}

// Configure the HTTP request pipeline
if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI();
}

app.UseCors("CorsPolicy");
// Uploaded files (product images, ticket attachments) are served from here — nosniff stops
// browsers from executing a mislabeled upload (e.g. an HTML/SVG payload) as its sniffed type.
app.UseStaticFiles(new StaticFileOptions
{
    OnPrepareResponse = ctx =>
    {
        ctx.Context.Response.Headers.Append("X-Content-Type-Options", "nosniff");
    }
});
app.UseAuthentication();
app.UseAuthorization();
app.MapControllers();
app.MapHub<ZewvronChatHub>("/zewvronChatHub");

Console.WriteLine("Zewvron API Service running on port 5001 - Database mode");
Console.WriteLine("Endpoints: /api/products, /api/categories, /api/orders, /api/inventory, /api/warranty, /api/repairs, /api/tickets, /api/notifications, /api/coupons, /api/specs, /api/uploads, /api/recommendations");
app.Run();
