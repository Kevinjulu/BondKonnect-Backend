<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use App\Models\KnowledgeChunk;
use App\Services\AiService;
use Illuminate\Support\Facades\File;

class KnowledgeBaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(AiService $aiService): void
    {
        $path = storage_path('app/ai_knowledge');
        if (!File::exists($path)) {
            $this->command->error("Knowledge path not found: {$path}");
            return;
        }

        $files = File::files($path);
        
        foreach ($files as $file) {
            $content = File::get($file);
            $fileName = $file->getFilename();
            
            $this->command->info("Processing: {$fileName}");

            // Simple splitting by Markdown headers (##)
            $sections = preg_split('/(?=## )/m', $content);

            foreach ($sections as $section) {
                if (empty(trim($section))) continue;

                // Extract first line as title
                $lines = explode("\n", trim($section));
                $title = str_replace(['#', '##'], '', $lines[0]);
                
                $this->command->info(" - Vectorizing: {$title}");
                
                $embedding = $aiService->getEmbedding($section);

                if ($embedding) {
                    KnowledgeChunk::updateOrCreate(
                        ['section_title' => trim($title), 'source_file' => $fileName],
                        ['content' => $section, 'embedding' => $embedding]
                    );
                } else {
                    $this->command->error("   Failed to get embedding for: {$title}");
                }
            }
        }
    }
}
