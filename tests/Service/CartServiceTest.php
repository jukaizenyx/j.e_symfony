<?php

namespace App\Tests\Service;

use App\Service\CartService;
use App\Service\StoreCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CartServiceTest extends TestCase
{
    public function testFallbackCatalogProductsCanBeAddedAndPricedInTheSessionCart(): void
    {
        $catalog = $this->createStub(StoreCatalog::class);
        $catalog->method('find')->willReturn([
            'id' => 1,
            'name' => 'Cream Moisturizer',
            'image' => 'images/first-png.svg',
            'description' => 'Original product description.',
            'sizes' => ['30ml' => 149],
        ]);
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $cart = new CartService($requestStack, $catalog);

        self::assertSame(['ok' => true], $cart->add(1, '30ml', 2));
        self::assertSame([
            'ok' => true,
            'items' => [[
                'key' => '1_30ml',
                'product_id' => 1,
                'name' => 'Cream Moisturizer',
                'image' => 'images/first-png.svg',
                'size' => '30ml',
                'price' => 149.0,
                'qty' => 2,
                'line_total' => 298.0,
            ]],
            'count' => 2,
            'subtotal' => 298.0,
        ], $cart->snapshot());
    }
}
