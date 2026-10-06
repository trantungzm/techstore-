using Microsoft.EntityFrameworkCore;
using Zewvron.Entities;
using System;
using System.Threading.Tasks;

namespace Zewvron.Repository.Authen
{
    public interface IRefreshTokenRepository
    {
        Task AddAsync(RefreshToken token);
        Task<RefreshToken?> GetActiveByHashAsync(string tokenHash);
        Task RevokeAsync(RefreshToken token);
    }

    public class RefreshTokenRepository : IRefreshTokenRepository
    {
        private readonly AppDbContext _context;

        public RefreshTokenRepository(AppDbContext context)
        {
            _context = context;
        }

        public async Task AddAsync(RefreshToken token)
        {
            _context.RefreshTokens.Add(token);
            await _context.SaveChangesAsync();
        }

        public async Task<RefreshToken?> GetActiveByHashAsync(string tokenHash)
        {
            var now = DateTime.UtcNow;
            return await _context.RefreshTokens
                .FirstOrDefaultAsync(t => t.TokenHash == tokenHash && t.RevokedAt == null && t.ExpiresAt > now);
        }

        public async Task RevokeAsync(RefreshToken token)
        {
            token.RevokedAt = DateTime.UtcNow;
            await _context.SaveChangesAsync();
        }
    }
}
