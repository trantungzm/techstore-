
using Zewvron.Common;

namespace Zewvron.LogService.Entities
{
    public class LogAction : Entity
    {
        public string Name { get; set; }
        public string Action { get; set; }
        public string IPAddress { get; set; }
        public string LocalName { get; set; }
    }
}
