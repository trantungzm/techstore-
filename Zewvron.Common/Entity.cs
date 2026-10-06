using MongoDB.Bson;
using MongoDB.Bson.Serialization.Attributes;
using System;

namespace Zewvron.Common
{
    public class Entity
    {
        [BsonRepresentation(BsonType.ObjectId)]
        public string Id { get; set; } = ObjectId.GenerateNewId().ToString();

        public DateTime CreatedDateTime { get; set; } = new DateTime();
        public string CreatedUser { get; set; }
    }
}
