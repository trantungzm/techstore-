using System;

namespace BaseCore.Entities
{
    // The raw refresh token value is never stored — only its SHA-256 hash (TokenHash), so a DB
    // leak alone can't be used to mint sessions.
    public class RefreshToken
    {
        public long Id { get; set; }
        public Guid UserId { get; set; }
        public string TokenHash { get; set; } = string.Empty;
        public DateTime ExpiresAt { get; set; }
        public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
        public DateTime? RevokedAt { get; set; }
    }
}
