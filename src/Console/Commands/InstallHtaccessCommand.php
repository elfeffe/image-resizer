<?php

namespace Elfeffe\ImageResizer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallHtaccessCommand extends Command
{
    public $signature = 'image-resizer:install-htaccess {--force : Force installation even if rules already exist}';

    public $description = 'Install Image Resizer htaccess optimization rules';

    public function handle()
    {
        $htaccessPath = public_path('.htaccess');
        $imageResizerRules = $this->getImageResizerRules();
        
        if (!File::exists($htaccessPath)) {
            $this->error('.htaccess file not found in public directory!');
            return 1;
        }

        $currentContent = File::get($htaccessPath);
        
        // Check if rules already exist
        if (str_contains($currentContent, 'Image Resizer Rule') && !$this->option('force')) {
            $this->info('Image Resizer rules already exist in .htaccess');
            $this->info('Use --force to reinstall them.');
            return 0;
        }

        // Remove existing rules if force is used
        if ($this->option('force')) {
            $currentContent = $this->removeExistingRules($currentContent);
        }

        // Insert rules after "RewriteEngine On"
        $newContent = $this->insertRulesAfterRewriteEngine($currentContent, $imageResizerRules);
        
        if ($newContent === $currentContent) {
            $this->error('Could not find "RewriteEngine On" in .htaccess file!');
            $this->info('Please add the rules manually after "RewriteEngine On"');
            $this->info('Rules to add:');
            $this->info($imageResizerRules);
            return 1;
        }

        // Create backup
        $backupPath = $htaccessPath . '.backup.' . date('Y-m-d-H-i-s');
        File::copy($htaccessPath, $backupPath);
        
        // Write new content
        File::put($htaccessPath, $newContent);
        
        $this->info('✅ Image Resizer htaccess rules installed successfully!');
        $this->info("📁 Backup created: {$backupPath}");
        $this->info('🚀 Your images will now be served with maximum performance!');
        
        return 0;
    }

    protected function getImageResizerRules(): string
    {
        return "
    # Image Resizer Performance Optimization
    # This rule serves cached images directly from filesystem when available,
    # falling back to Laravel dynamic processing when not cached yet.
    
    # Image Resizer Rule - Check for cached file first, then fallback to Laravel
    RewriteCond %{REQUEST_URI} ^/image_resizer/([^/]*)/w/([^/]*)/h/([^/]*)/([^/]*)/([^.]*)\.(.*)$
    RewriteCond %{DOCUMENT_ROOT}/storage/image_resizer/%1/%2x%3/%4.%6 -f
    RewriteRule ^image_resizer/([^/]*)/w/([^/]*)/h/([^/]*)/([^/]*)/([^.]*)\.(.*)$ /storage/image_resizer/$1/$2x$3/$4.$6 [L]
";
    }

    protected function removeExistingRules(string $content): string
    {
        // Remove existing Image Resizer rules
        $pattern = '/\s*# Image Resizer Performance Optimization.*?RewriteRule[^\n]*\[L\]\s*/s';
        return preg_replace($pattern, '', $content);
    }

    protected function insertRulesAfterRewriteEngine(string $content, string $rules): string
    {
        // Find "RewriteEngine On" and insert rules after it
        $pattern = '/(RewriteEngine\s+On)/i';
        
        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, '$1' . $rules, $content);
        }
        
        return $content;
    }
} 
