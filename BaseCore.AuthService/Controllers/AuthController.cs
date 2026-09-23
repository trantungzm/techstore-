using Microsoft.AspNetCore.Mvc;
using BaseCore.Common;
using BaseCore.Entities;
using BaseCore.Repository;
using BaseCore.Repository.Authen;
using BaseCore.Services.Authen;
using Microsoft.EntityFrameworkCore;
using System.Security.Cryptography;
using System.Text;
using System.Threading.Tasks;

namespace BaseCore.AuthService.Controllers
{
    [Route("api/[controller]")]
    [ApiController]
    public class AuthController : ControllerBase
    {
        private readonly IUserService _userService;
        private readonly IRefreshTokenRepository _refreshTokenRepository;
        private readonly IConfiguration _configuration;
        private readonly IServiceProvider _serviceProvider;
        // Access token is short-lived; a stolen token via XSS is only usable for this window.
        private const int TokenExpirationMinutes = 60;
        // Refresh token covers the actual session length; stored only as a hash, rotated on use.
        private const int RefreshTokenExpirationDays = 7;

        public AuthController(IUserService userService, IRefreshTokenRepository refreshTokenRepository, IConfiguration configuration, IServiceProvider serviceProvider)
        {
            _userService = userService;
            _refreshTokenRepository = refreshTokenRepository;
            _configuration = configuration;
            _serviceProvider = serviceProvider;
        }

        [HttpPost("login")]
        public async Task<IActionResult> Login([FromBody] LoginRequest request)
        {
            if (request == null || string.IsNullOrEmpty(request.Username) || string.IsNullOrEmpty(request.Password))
            {
                return BadRequest(new { message = "Username and password are required" });
            }

            var user = await _userService.Authenticate(request.Username, request.Password);

            if (user == null)
            {
                return Unauthorized(new { message = "Invalid username or password" });
            }

            return Ok(await BuildLoginResponseAsync(user));
        }

        [HttpPost("refresh")]
        public async Task<IActionResult> Refresh([FromBody] RefreshRequest request)
        {
            if (request == null || string.IsNullOrWhiteSpace(request.RefreshToken))
            {
                return BadRequest(new { message = "Refresh token is required" });
            }

            var existing = await _refreshTokenRepository.GetActiveByHashAsync(HashRefreshToken(request.RefreshToken));
            if (existing == null)
            {
                return Unauthorized(new { message = "Invalid or expired refresh token" });
            }

            var user = await _userService.GetById(existing.UserId);
            if (user == null || !user.IsActive)
            {
                await _refreshTokenRepository.RevokeAsync(existing);
                return Unauthorized(new { message = "Invalid or expired refresh token" });
            }

            // Rotation: the presented refresh token is single-use — revoke it before issuing the
            // replacement, so a leaked-but-already-used token can't be replayed.
            await _refreshTokenRepository.RevokeAsync(existing);

            return Ok(await BuildLoginResponseAsync(user));
        }

        [HttpPost("logout")]
        public async Task<IActionResult> Logout([FromBody] RefreshRequest request)
        {
            if (request != null && !string.IsNullOrWhiteSpace(request.RefreshToken))
            {
                var existing = await _refreshTokenRepository.GetActiveByHashAsync(HashRefreshToken(request.RefreshToken));
                if (existing != null)
                {
                    await _refreshTokenRepository.RevokeAsync(existing);
                }
            }

            return Ok(new { message = "Logged out" });
        }

        private async Task<LoginResponse> BuildLoginResponseAsync(User user)
        {
            // Generate JWT access token
            var secretKey = _configuration["Jwt:SecretKey"] ?? _configuration["AppSettings:Secret"]
                ?? throw new InvalidOperationException("Jwt:SecretKey chưa được cấu hình (appsettings).");
            var issuer = _configuration["Jwt:Issuer"] ?? "BaseCore";
            var audience = _configuration["Jwt:Audience"] ?? "BaseCore.WebClient";
            var roleName = await ResolveRoleName(user.UserType);
            var token = TokenHelper.GenerateToken(
                secretKey,
                TokenExpirationMinutes,
                user.Id.ToString(),
                user.UserName,
                roleName,
                issuer,
                audience
            );

            var rawRefreshToken = GenerateRefreshTokenValue();
            await _refreshTokenRepository.AddAsync(new RefreshToken
            {
                UserId = user.Id,
                TokenHash = HashRefreshToken(rawRefreshToken),
                ExpiresAt = DateTime.UtcNow.AddDays(RefreshTokenExpirationDays)
            });

            return new LoginResponse
            {
                Token = token,
                RefreshToken = rawRefreshToken,
                UserId = user.Id.ToString(),
                Username = user.UserName,
                Name = user.Name,
                Email = user.Email,
                Role = roleName,
                ExpiresIn = TokenExpirationMinutes * 60,
                User = new LoginUserDto
                {
                    Id = user.Id.ToString(),
                    UserName = user.UserName,
                    Email = user.Email,
                    PhoneNumber = user.Phone,
                    DateOfBirth = user.DateOfBirth,
                    Role = roleName,
                    IsActive = user.IsActive,
                    CreatedAt = user.Created
                }
            };
        }

        // Never store the raw refresh token — only its hash, so a DB leak can't be replayed.
        private static string GenerateRefreshTokenValue()
        {
            var bytes = RandomNumberGenerator.GetBytes(64);
            return Convert.ToBase64String(bytes).Replace('+', '-').Replace('/', '_').TrimEnd('=');
        }

        private static string HashRefreshToken(string rawToken)
        {
            var hash = SHA256.HashData(Encoding.UTF8.GetBytes(rawToken));
            return Convert.ToBase64String(hash);
        }

        private async Task<string> ResolveRoleName(int userType)
        {
            var db = _serviceProvider.GetService<AppDbContext>();
            if (db == null)
            {
                return userType == 1 ? "Admin" : "User";
            }

            var roleName = await db.Roles
                .Where(r => r.IsActive && !r.IsDeleted && r.RoleType == userType)
                .Select(r => r.Name)
                .FirstOrDefaultAsync();

            return roleName ?? (userType == 1 ? "Admin" : "User");
        }

        [HttpPost("register")]
        public async Task<IActionResult> Register([FromBody] RegisterRequest request)
        {
            if (request == null)
            {
                return BadRequest(new { message = "Invalid request" });
            }

            if (string.IsNullOrEmpty(request.Username) || string.IsNullOrEmpty(request.Password))
            {
                return BadRequest(new { message = "Username and password are required" });
            }

            if (request.Password.Length < 6)
            {
                return BadRequest(new { message = "Password must be at least 6 characters" });
            }

            try
            {
                var user = new BaseCore.Entities.User
                {
                    UserName = request.Username,
                    Name = request.Name ?? request.Username,
                    Email = request.Email,
                    Phone = request.Phone,
                    DateOfBirth = request.DateOfBirth?.Date,
                    UserType = 0 // Default to regular user
                };

                var createdUser = await _userService.Create(user, request.Password);

                return Ok(new { message = "Registration successful", userId = createdUser.Id });
            }
            catch (System.Exception ex)
            {
                return BadRequest(new { message = "Registration failed: " + ex.Message });
            }
        }
    }

    public class LoginRequest
    {
        public string Username { get; set; }
        public string Password { get; set; }
    }

    public class RefreshRequest
    {
        public string RefreshToken { get; set; }
    }

    public class LoginResponse
    {
        public string Token { get; set; }
        public string RefreshToken { get; set; }
        public string UserId { get; set; }
        public string Username { get; set; }
        public string Name { get; set; }
        public string Email { get; set; }
        public string Role { get; set; }
        public int ExpiresIn { get; set; }
        public LoginUserDto User { get; set; }
    }

    public class LoginUserDto
    {
        public string Id { get; set; }
        public string UserName { get; set; }
        public string Email { get; set; }
        public string PhoneNumber { get; set; }
        public DateTime? DateOfBirth { get; set; }
        public string Role { get; set; }
        public bool IsActive { get; set; }
        public DateTime CreatedAt { get; set; }
    }

    public class RegisterRequest
    {
        public string Username { get; set; }
        public string Password { get; set; }
        public string Name { get; set; }
        public string Email { get; set; }
        public string Phone { get; set; }
        public DateTime? DateOfBirth { get; set; }
    }
}
