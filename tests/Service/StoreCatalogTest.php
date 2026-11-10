<?php

namespace App\Tests\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\ProductCatalog;
use App\Service\StoreCatalog;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StoreCatalogTest extends TestCase
{
    public function testEmptyDatabaseUsesOriginalCatalogForListingAndDetails(): void
    {
        $fallback = [
            [
                'id' => 1,
                'name' => 'Cream Moisturizer',
                'image' => 'images/first-png.svg',
                'description' => 'Original product description.',
                'sizes' => ['30ml' => 149],
            ],
        ];
        $repository = $this->createMock(ProductRepository::class);
        $repository->expects(self::once())->method('findBy')->with([], ['id' => 'DESC'])->willReturn([]);
        $repository->expects(self::once())->method('find')->with(1)->willReturn(null);
        $repository->expects(self::once())->method('count')->with([])->willReturn(0);

        $fallbackCatalog = $this->createMock(ProductCatalog::class);
        $fallbackCatalog->expects(self::once())->method('all')->willReturn($fallback);
        $fallbackCatalog->expects(self::once())->method('find')->with(1)->willReturn($fallback[0]);

        $catalog = new StoreCatalog($repository, $fallbackCatalog, $this->createStub(LoggerInterface::class));

        self::assertSame(['products' => $fallback, 'usingFallback' => true], $catalog->listing());
        self::assertSame($fallback[0], $catalog->find(1));
    }

    public function testDatabaseProductsTakePrecedenceOverFallbackCatalog(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(9);
        $product->method('getName')->willReturn('Database product');
        $product->method('getImage')->willReturn('images/database-product.png');
        $product->method('getDescription')->willReturn('Database description.');
        $product->method('getSizes')->willReturn('{"50ml":199}');

        $repository = $this->createMock(ProductRepository::class);
        $repository->expects(self::once())->method('findBy')->with([], ['id' => 'DESC'])->willReturn([$product]);
        $repository->expects(self::once())->method('find')->with(9)->willReturn($product);

        $fallbackCatalog = $this->createMock(ProductCatalog::class);
        $fallbackCatalog->expects(self::never())->method('all');
        $fallbackCatalog->expects(self::never())->method('find');

        $catalog = new StoreCatalog($repository, $fallbackCatalog, $this->createStub(LoggerInterface::class));
        $expected = [
            'id' => 9,
            'name' => 'Database product',
            'image' => 'images/database-product.png',
            'description' => 'Database description.',
            'sizes' => ['50ml' => 199],
        ];

        self::assertSame(['products' => [$expected], 'usingFallback' => false], $catalog->listing());
        self::assertSame($expected, $catalog->find(9));
    }

    public function testUnknownDatabaseProductDoesNotResolveToFallbackWhenDatabaseHasProducts(): void
    {
        $repository = $this->createMock(ProductRepository::class);
        $repository->expects(self::once())->method('find')->with(100)->willReturn(null);
        $repository->expects(self::once())->method('count')->with([])->willReturn(1);

        $fallbackCatalog = $this->createMock(ProductCatalog::class);
        $fallbackCatalog->expects(self::never())->method('find');

        $catalog = new StoreCatalog($repository, $fallbackCatalog, $this->createStub(LoggerInterface::class));

        self::assertNull($catalog->find(100));
    }
}
