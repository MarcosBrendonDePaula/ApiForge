<?php

namespace MarcosBrendon\ApiForge\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use MarcosBrendon\ApiForge\Services\DocumentationGeneratorService;
use MarcosBrendon\ApiForge\Traits\HasAdvancedFilters;

class GenerateDocumentationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'apiforge:docs 
                           {controller? : Specific controller class to generate docs for}
                           {--output= : Output directory (default: storage/app/docs)}
                           {--format=json : Output format (json, yaml, html)}
                           {--endpoint= : Specific endpoint path}
                           {--force : Overwrite existing documentation}
                           {--cache-clear : Clear documentation cache before generation}
                           {--all : Generate for all controllers with HasAdvancedFilters trait}
                           {--scan-path= : Path to scan for controllers (default: app/Http/Controllers)}';

    /**
     * The console command description.
     */
    protected $description = 'Generate comprehensive OpenAPI documentation using LLM enhancement';

    protected DocumentationGeneratorService $docGenerator;

    public function __construct(DocumentationGeneratorService $docGenerator)
    {
        parent::__construct();
        $this->docGenerator = $docGenerator;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 ApiForge Documentation Generator');
        $this->info('===================================');

        try {
            // Clear cache if requested
            if ($this->option('cache-clear')) {
                $this->info('🧹 Clearing documentation cache...');
                $this->docGenerator->clearCache();
                $this->info('✅ Cache cleared');
            }

            $controller = $this->argument('controller');
            $outputPath = $this->option('output') ?: storage_path('app/docs');
            $format = $this->option('format');
            $endpoint = $this->option('endpoint');

            // Ensure output directory exists
            if (!File::exists($outputPath)) {
                File::makeDirectory($outputPath, 0755, true);
                $this->info("📁 Created output directory: {$outputPath}");
            }

            if ($this->option('all')) {
                $this->generateForAllControllers($outputPath, $format);
            } elseif ($controller) {
                $this->generateForSingleController($controller, $endpoint, $outputPath, $format);
            } else {
                $this->generateInteractively($outputPath, $format);
            }

            $this->info('');
            $this->info('✅ Documentation generation completed!');
            $this->info("📂 Output location: {$outputPath}");

        } catch (\Exception $e) {
            $this->error('❌ Documentation generation failed: ' . $e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }
            
            return 1;
        }

        return 0;
    }

    /**
     * Generate documentation for all controllers with HasAdvancedFilters trait
     */
    protected function generateForAllControllers(string $outputPath, string $format): void
    {
        $controllers = $this->findControllersWithTrait();
        
        if (empty($controllers)) {
            $this->warn('⚠️  No controllers found with HasAdvancedFilters trait');
            return;
        }

        $this->info("🔍 Found " . count($controllers) . " controllers with HasAdvancedFilters trait");
        
        $progressBar = $this->output->createProgressBar(count($controllers));
        $progressBar->start();

        $generated = 0;
        foreach ($controllers as $controllerInfo) {
            try {
                $this->generateDocumentationForController(
                    $controllerInfo['class'],
                    $controllerInfo['endpoint'],
                    $outputPath,
                    $format
                );
                $generated++;
            } catch (\Exception $e) {
                $this->warn("\n⚠️  Failed to generate docs for {$controllerInfo['class']}: {$e->getMessage()}");
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\n✅ Generated documentation for {$generated} controllers");
    }

    /**
     * Generate documentation for a single controller
     */
    protected function generateForSingleController(
        string $controller,
        ?string $endpoint,
        string $outputPath,
        string $format
    ): void {
        if (!class_exists($controller)) {
            $this->error("❌ Controller class not found: {$controller}");
            return;
        }

        if (!$this->controllerHasTrait($controller)) {
            $this->warn("⚠️  Controller {$controller} does not use HasAdvancedFilters trait");
            
            if (!$this->confirm('Continue anyway?')) {
                return;
            }
        }

        $endpoint = $endpoint ?: $this->guessEndpointFromController($controller);
        
        $this->info("📝 Generating documentation for: {$controller}");
        $this->info("🔗 Endpoint: {$endpoint}");

        $this->generateDocumentationForController($controller, $endpoint, $outputPath, $format);
    }

    /**
     * Generate documentation interactively
     */
    protected function generateInteractively(string $outputPath, string $format): void
    {
        $controllers = $this->findControllersWithTrait();
        
        if (empty($controllers)) {
            $this->warn('⚠️  No controllers found with HasAdvancedFilters trait');
            $this->info('💡 Use --scan-path option to scan a different directory');
            return;
        }

        $options = [];
        $optionMap = [];
        
        foreach ($controllers as $index => $controllerInfo) {
            $name = class_basename($controllerInfo['class']);
            $options[] = "{$name} ({$controllerInfo['endpoint']})";
            $optionMap[$index] = $controllerInfo;
        }

        $options[] = 'All controllers';
        $allIndex = count($options) - 1;

        $choice = $this->choice(
            'Which controller would you like to generate documentation for?',
            $options
        );

        $selectedIndex = array_search($choice, $options);

        if ($selectedIndex === $allIndex) {
            $this->generateForAllControllers($outputPath, $format);
        } else {
            $controllerInfo = $optionMap[$selectedIndex];
            $this->generateDocumentationForController(
                $controllerInfo['class'],
                $controllerInfo['endpoint'],
                $outputPath,
                $format
            );
        }
    }

    /**
     * Generate documentation for a specific controller
     */
    protected function generateDocumentationForController(
        string $controllerClass,
        string $endpoint,
        string $outputPath,
        string $format
    ): void {
        $this->info("⚙️  Processing {$controllerClass}...");

        // Check cache first
        $cached = $this->docGenerator->getCachedDocumentation($controllerClass, $endpoint);
        
        if ($cached && !$this->option('force')) {
            $this->info('📋 Using cached documentation');
            $documentation = $cached;
        } else {
            $this->info('🤖 Generating enhanced documentation with LLM...');
            
            // Show spinner while processing
            $this->withSpinner(function () use ($controllerClass, $endpoint, &$documentation) {
                $documentation = $this->docGenerator->generateOpenApiDocumentation(
                    $controllerClass,
                    $endpoint
                );
            }, '🤖 Processing with AI...');
        }

        // Save documentation
        $filename = $this->generateFilename($controllerClass, $format);
        $filePath = $outputPath . '/' . $filename;

        $this->saveDocumentation($documentation, $filePath, $format);
        
        $this->info("💾 Saved: {$filename}");

        // Generate additional formats if requested
        if ($format === 'json') {
            $this->generateAdditionalFormats($documentation, $outputPath, $controllerClass);
        }
    }

    /**
     * Find all controllers that use the HasAdvancedFilters trait
     */
    protected function findControllersWithTrait(): array
    {
        $scanPath = $this->option('scan-path') ?: app_path('Http/Controllers');
        
        if (!File::exists($scanPath)) {
            $this->warn("⚠️  Scan path does not exist: {$scanPath}");
            return [];
        }

        $controllers = [];
        $files = File::allFiles($scanPath);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $className = $this->getClassNameFromFile($file->getPathname());
                
                if ($className && $this->controllerHasTrait($className)) {
                    $controllers[] = [
                        'class' => $className,
                        'endpoint' => $this->guessEndpointFromController($className),
                        'file' => $file->getPathname()
                    ];
                }
            }
        }

        return $controllers;
    }

    /**
     * Get class name from PHP file
     */
    protected function getClassNameFromFile(string $filePath): ?string
    {
        $content = File::get($filePath);
        
        // Extract namespace
        if (preg_match('/namespace\s+(.+?);/', $content, $namespaceMatches)) {
            $namespace = $namespaceMatches[1];
        } else {
            $namespace = '';
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/', $content, $classMatches)) {
            $className = $classMatches[1];
            return $namespace ? $namespace . '\\' . $className : $className;
        }

        return null;
    }

    /**
     * Check if controller has the HasAdvancedFilters trait
     */
    protected function controllerHasTrait(string $controllerClass): bool
    {
        if (!class_exists($controllerClass)) {
            return false;
        }

        $traits = class_uses_recursive($controllerClass);
        return in_array(HasAdvancedFilters::class, $traits);
    }

    /**
     * Guess endpoint from controller class name
     */
    protected function guessEndpointFromController(string $controllerClass): string
    {
        $className = class_basename($controllerClass);
        $resource = str_replace('Controller', '', $className);
        return '/api/' . Str::kebab(Str::plural($resource));
    }

    /**
     * Generate filename for documentation
     */
    protected function generateFilename(string $controllerClass, string $format): string
    {
        $className = class_basename($controllerClass);
        $resource = str_replace('Controller', '', $className);
        $kebabCase = Str::kebab($resource);
        
        return "apiforge-{$kebabCase}.{$format}";
    }

    /**
     * Save documentation in specified format
     */
    protected function saveDocumentation(array $documentation, string $filePath, string $format): void
    {
        switch ($format) {
            case 'json':
                File::put($filePath, json_encode($documentation, JSON_PRETTY_PRINT));
                break;
                
            case 'yaml':
                // You might want to add a YAML library for this
                $yamlContent = $this->arrayToYaml($documentation);
                File::put($filePath, $yamlContent);
                break;
                
            case 'html':
                $htmlContent = $this->generateHtmlDocumentation($documentation);
                File::put($filePath, $htmlContent);
                break;
                
            default:
                throw new \InvalidArgumentException("Unsupported format: {$format}");
        }
    }

    /**
     * Convert array to YAML (simple implementation)
     */
    protected function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $indentStr = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $yaml .= "{$indentStr}{$key}:\n";
                $yaml .= $this->arrayToYaml($value, $indent + 1);
            } else {
                $yaml .= "{$indentStr}{$key}: " . $this->yamlValue($value) . "\n";
            }
        }

        return $yaml;
    }

    /**
     * Format value for YAML
     */
    protected function yamlValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_null($value)) {
            return 'null';
        }
        
        if (is_string($value) && (strpos($value, ':') !== false || strpos($value, ' ') !== false)) {
            return '"' . addslashes($value) . '"';
        }
        
        return (string) $value;
    }

    /**
     * Generate HTML documentation
     */
    protected function generateHtmlDocumentation(array $documentation): string
    {
        $title = $documentation['info']['title'] ?? 'API Documentation';
        $description = $documentation['info']['description'] ?? '';
        
        $html = "<!DOCTYPE html>\n";
        $html .= "<html lang='en'>\n";
        $html .= "<head>\n";
        $html .= "<meta charset='UTF-8'>\n";
        $html .= "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
        $html .= "<title>{$title}</title>\n";
        $html .= "<style>\n";
        $html .= $this->getHtmlStyles();
        $html .= "</style>\n";
        $html .= "</head>\n";
        $html .= "<body>\n";
        $html .= "<div class='container'>\n";
        $html .= "<h1>{$title}</h1>\n";
        $html .= "<p>{$description}</p>\n";
        $html .= "<pre><code>" . json_encode($documentation, JSON_PRETTY_PRINT) . "</code></pre>\n";
        $html .= "</div>\n";
        $html .= "</body>\n";
        $html .= "</html>\n";
        
        return $html;
    }

    /**
     * Get CSS styles for HTML documentation
     */
    protected function getHtmlStyles(): string
    {
        return '
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        
        pre {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 20px;
            overflow-x: auto;
        }
        
        code {
            font-family: "SF Mono", Monaco, Inconsolata, monospace;
            font-size: 14px;
        }
        ';
    }

    /**
     * Generate additional formats
     */
    protected function generateAdditionalFormats(array $documentation, string $outputPath, string $controllerClass): void
    {
        if ($this->confirm('Generate YAML version as well?', false)) {
            $yamlPath = $outputPath . '/' . str_replace('.json', '.yaml', $this->generateFilename($controllerClass, 'yaml'));
            $this->saveDocumentation($documentation, $yamlPath, 'yaml');
            $this->info("💾 Also saved YAML: " . basename($yamlPath));
        }

        if ($this->confirm('Generate HTML preview?', false)) {
            $htmlPath = $outputPath . '/' . str_replace('.json', '.html', $this->generateFilename($controllerClass, 'html'));
            $this->saveDocumentation($documentation, $htmlPath, 'html');
            $this->info("💾 Also saved HTML: " . basename($htmlPath));
        }
    }

    /**
     * Show spinner while processing
     */
    protected function withSpinner(callable $callback, string $message = 'Processing...'): void
    {
        $spinner = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $i = 0;
        
        $this->output->write($message . ' ');
        
        // Start the callback in a way that allows us to show progress
        // In a real implementation, you might want to use a proper async approach
        $callback();
        
        $this->output->writeln('✅');
    }
}