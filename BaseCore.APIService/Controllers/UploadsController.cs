using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;

namespace BaseCore.APIService.Controllers
{
    [Route("api/[controller]")]
    [ApiController]
    public class UploadsController : ControllerBase
    {
        private const long MaxFileSize = 5 * 1024 * 1024;
        private readonly IWebHostEnvironment _environment;

        public UploadsController(IWebHostEnvironment environment)
        {
            _environment = environment;
        }

        /// <summary>
        /// Upload product images (Admin/Warehouse only — this is only ever called from the admin
        /// product form, no reason to accept anonymous uploads to a public-facing path).
        /// </summary>
        [HttpPost("product-images")]
        [Authorize(Roles = "Admin,Warehouse")]
        [RequestSizeLimit(MaxFileSize * 10)]
        public async Task<IActionResult> UploadProductImages([FromForm] List<IFormFile> files)
        {
            if (files == null || files.Count == 0)
            {
                return BadRequest(new { message = "No files uploaded" });
            }

            var uploadRoot = Path.Combine(_environment.WebRootPath ?? Path.Combine(_environment.ContentRootPath, "wwwroot"), "uploads", "products");
            Directory.CreateDirectory(uploadRoot);

            var urls = new List<string>();
            foreach (var file in files)
            {
                if (file.Length <= 0) continue;
                if (file.Length > MaxFileSize) return BadRequest(new { message = "File size must be 5MB or less" });

                var bytes = await ReadAllBytesAsync(file);
                var extension = DetectImageExtension(bytes);
                if (extension == null)
                {
                    return BadRequest(new { message = "File content is not a recognized image (jpg, png or webp)" });
                }

                var fileName = $"{Guid.NewGuid():N}{extension}";
                var fullPath = Path.Combine(uploadRoot, fileName);
                await System.IO.File.WriteAllBytesAsync(fullPath, bytes);
                urls.Add($"/uploads/products/{fileName}");
            }

            return Ok(new { url = urls.FirstOrDefault(), urls });
        }

        [HttpPost("ticket-attachments")]
        [AllowAnonymous]
        [RequestSizeLimit(10 * 1024 * 1024)]
        public async Task<IActionResult> UploadTicketAttachments([FromForm] List<IFormFile> files)
        {
            if (files == null || files.Count == 0)
            {
                return BadRequest(new { message = "No files uploaded" });
            }

            var uploadRoot = Path.Combine(_environment.WebRootPath ?? Path.Combine(_environment.ContentRootPath, "wwwroot"), "uploads", "tickets");
            Directory.CreateDirectory(uploadRoot);

            var urls = new List<string>();
            foreach (var file in files)
            {
                if (file.Length <= 0) continue;
                if (file.Length > 10 * 1024 * 1024) return BadRequest(new { message = "File size must be 10MB or less" });

                var bytes = await ReadAllBytesAsync(file);
                var extension = DetectImageExtension(bytes) ?? (IsPdf(bytes) ? ".pdf" : null);
                if (extension == null)
                {
                    return BadRequest(new { message = "File content is not a recognized image (jpg, png, webp) or PDF" });
                }

                var fileName = $"{Guid.NewGuid():N}{extension}";
                var fullPath = Path.Combine(uploadRoot, fileName);
                await System.IO.File.WriteAllBytesAsync(fullPath, bytes);
                urls.Add($"/uploads/tickets/{fileName}");
            }

            return Ok(new { url = urls.FirstOrDefault(), urls });
        }

        private static async Task<byte[]> ReadAllBytesAsync(IFormFile file)
        {
            using var memoryStream = new MemoryStream();
            await file.CopyToAsync(memoryStream);
            return memoryStream.ToArray();
        }

        // Extension is derived from the actual file bytes (magic numbers), never from the
        // client-supplied Content-Type header or original filename — both are attacker-controlled
        // and previously let a spoofed image/png upload land on disk as .svg/.html.
        private static string? DetectImageExtension(byte[] bytes)
        {
            if (bytes.Length >= 3 && bytes[0] == 0xFF && bytes[1] == 0xD8 && bytes[2] == 0xFF)
            {
                return ".jpg";
            }

            if (bytes.Length >= 8 &&
                bytes[0] == 0x89 && bytes[1] == 0x50 && bytes[2] == 0x4E && bytes[3] == 0x47 &&
                bytes[4] == 0x0D && bytes[5] == 0x0A && bytes[6] == 0x1A && bytes[7] == 0x0A)
            {
                return ".png";
            }

            if (bytes.Length >= 12 &&
                bytes[0] == (byte)'R' && bytes[1] == (byte)'I' && bytes[2] == (byte)'F' && bytes[3] == (byte)'F' &&
                bytes[8] == (byte)'W' && bytes[9] == (byte)'E' && bytes[10] == (byte)'B' && bytes[11] == (byte)'P')
            {
                return ".webp";
            }

            return null;
        }

        private static bool IsPdf(byte[] bytes)
        {
            return bytes.Length >= 5 &&
                   bytes[0] == (byte)'%' && bytes[1] == (byte)'P' && bytes[2] == (byte)'D' && bytes[3] == (byte)'F' && bytes[4] == (byte)'-';
        }
    }
}
