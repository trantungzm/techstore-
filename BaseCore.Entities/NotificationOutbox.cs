namespace BaseCore.Entities
{
    public class NotificationOutbox
    {
        public long Id { get; set; }
        public Guid EventId { get; set; } = Guid.NewGuid();
        public string EventType { get; set; } = string.Empty;
        public string AggregateType { get; set; } = string.Empty;
        public string AggregateId { get; set; } = string.Empty;
        public Guid? UserId { get; set; }
        public string Title { get; set; } = string.Empty;
        public string Message { get; set; } = string.Empty;
        public string? PayloadJson { get; set; }
        public string Status { get; set; } = "Pending";
        public DateTime AvailableAt { get; set; } = DateTime.UtcNow;
        public DateTime? ProcessedAt { get; set; }
        public string? LastError { get; set; }
        public int RetryCount { get; set; }
        public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    }
}
