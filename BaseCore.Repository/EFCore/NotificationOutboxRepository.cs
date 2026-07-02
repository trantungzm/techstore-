using BaseCore.Entities;
using Microsoft.EntityFrameworkCore;

namespace BaseCore.Repository.EFCore
{
    public interface INotificationOutboxRepositoryEF : IRepository<NotificationOutbox>
    {
        Task<List<NotificationOutbox>> GetPendingAsync(int batchSize, DateTime utcNow);
    }

    public class NotificationOutboxRepositoryEF : Repository<NotificationOutbox>, INotificationOutboxRepositoryEF
    {
        public NotificationOutboxRepositoryEF(AppDbContext context) : base(context) { }

        public Task<List<NotificationOutbox>> GetPendingAsync(int batchSize, DateTime utcNow)
        {
            var size = Math.Clamp(batchSize, 1, 500);
            return _dbSet
                .Where(x => x.Status == "Pending" && x.AvailableAt <= utcNow)
                .OrderBy(x => x.AvailableAt)
                .ThenBy(x => x.Id)
                .Take(size)
                .ToListAsync();
        }
    }
}
