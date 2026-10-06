using Zewvron.Entities;
using Zewvron.DTO.Store;
using System.Collections.Generic;
using System.Threading.Tasks;

namespace Zewvron.Services
{
    public interface ICategoryService
    {
        Task<List<Category>> GetAllAsync();
        Task<Category?> GetByIdAsync(int id);
        Task<Category?> GetByNameAsync(string name);
        Task<Category> CreateAsync(CategoryUpsertDto dto);
        Task<Category?> UpdateAsync(int id, CategoryUpsertDto dto);
        Task<bool> DeleteAsync(int id);
    }
}
