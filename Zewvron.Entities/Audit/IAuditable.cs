using System;

namespace Zewvron.Entities.Audit
{
    public interface IAuditable
    {
        string CreatedBy { get; set; }
        DateTime Created { get; set; }
        string ModifiedBy { get; set; }
        DateTime Modified { get; set; }
        bool IsDeleted { get; set; }
    }
}
