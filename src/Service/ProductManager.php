<?php

namespace App\Service;

use App\Entity\Product;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProductManager
{
    /**
     * @param list<array{size?: string, price?: int|float|string|null}> $rows
     */
    public function applySizes(Product $product, array $rows): bool
    {
        $sizes = [];
        foreach ($rows as $row) {
            $size = trim((string) ($row['size'] ?? ''));
            $price = $row['price'] ?? null;
            if ($size !== '' && $price !== null && $price !== '') {
                $sizes[$size] = (float) $price;
            }
        }

        if ($sizes === []) {
            return false;
        }
        foreach ($sizes as $size => $price) {
            if (!is_numeric($price) || $price < 0 || !is_string($size) || trim($size) === '') {
                return false;
            }
        }

        $product->setSizes(json_encode($sizes, JSON_THROW_ON_ERROR));
        $product->setPrice(number_format((float) reset($sizes), 2, '.', ''));

        return true;
    }

    public function storeImage(UploadedFile $imageFile): string
    {
        $extension = $imageFile->guessExtension();
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'bmp'], true)) {
            throw new \RuntimeException('The uploaded image type could not be safely identified.');
        }

        $directory = dirname(__DIR__, 2).'/public/uploads/products';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('The product image upload directory could not be created.');
        }

        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $imageFile->move($directory, $filename);

        return 'uploads/products/'.$filename;
    }

    public function removeStoredImage(string $imagePath): void
    {
        if (!preg_match('/^uploads\/products\/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp|gif|avif|bmp)$/', $imagePath)) {
            return;
        }

        $absolutePath = dirname(__DIR__, 2).'/public/'.$imagePath;
        if (is_file($absolutePath) && !unlink($absolutePath)) {
            throw new \RuntimeException('The uploaded product image could not be removed.');
        }
    }
}
