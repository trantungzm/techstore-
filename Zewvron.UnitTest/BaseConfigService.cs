using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Options;
using System;
using Zewvron.Common;

namespace Zewvron.UnitTest
{
    public class BaseConfigService
    {
        public IOptions<AppSettings> Option;
        public readonly IConfiguration ConfigurationRoot;

        public BaseConfigService()
        {
            var builder = new ConfigurationBuilder()
                .SetBasePath(AppDomain.CurrentDomain.BaseDirectory)
                .AddJsonFile("appsettings.json", optional: false, reloadOnChange: true);

            ConfigurationRoot = builder.Build();
            Option = Options.Create(new AppSettings
            {
                Secret = ""
            });
        }
    }
}
