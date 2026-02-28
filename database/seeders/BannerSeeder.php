<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        DB::table('ad_banner')->delete();

        Storage::disk('public')->makeDirectory('banners');

        $banners = [
            ['name' => 'Promo Nutrisi Kehamilan', 'photo' => 'banners/promo-nutrisi.png', 'url' => 'https://shope.ee/nutrisi'],
            ['name' => 'Diskon Pakaian Hamil', 'photo' => 'banners/diskon-pakaian.png', 'url' => 'https://shope.ee/pakaian'],
            ['name' => 'Festival Kelahiran Bayi', 'photo' => 'banners/festival-bayi.png', 'url' => 'https://shope.ee/bayi'],
        ];

        foreach ($banners as $banner) {
            if (!Storage::disk('public')->exists($banner['photo'])) {
                $this->generatePlaceholderImage($banner['photo'], $banner['name']);
            }

            DB::table('ad_banner')->insert([
                'name' => $banner['name'],
                'photo' => $banner['photo'],
                'url' => $banner['url'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command->info("Successfully created " . count($banners) . " banners.");
    }

    private function generatePlaceholderImage(string $path, string $label): void
    {
        $width = 800;
        $height = 400;
        $img = imagecreatetruecolor($width, $height);

        // Background color (blueish for banners)
        $bg = imagecolorallocate($img, 100, 150, 250);
        imagefill($img, 0, 0, $bg);

        // Text color
        $white = imagecolorallocate($img, 255, 255, 255);

        $fontSize = 5;
        $textWidth = imagefontwidth($fontSize) * strlen($label);
        $x = ($width - $textWidth) / 2;
        $y = ($height - imagefontheight($fontSize)) / 2;
        
        imagestring($img, $fontSize, (int)$x, (int)$y, $label, $white);

        ob_start();
        imagepng($img);
        $pngData = ob_get_clean();
        imagedestroy($img);

        Storage::disk('public')->put($path, $pngData);
    }
}
