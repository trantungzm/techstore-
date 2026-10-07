
using Zewvron.Common;

namespace Zewvron.LogService.Entities
{
    public class LogError: Entity
    {
        public string Header { get; set; }
        public string Body { get; set; }
        public string Message { get; set; }
    }

}
