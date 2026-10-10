<?php

use App\Filters\PrivateResponseFilter;
use App\Services\CampaignLocationService;
use App\Services\OtpService;
use App\Services\ProductImageService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SecurityBoundaryTest extends CIUnitTestCase
{
    public static function maliciousPhones(): array
    {
        return array_map(static fn ($value) => [$value], ['abc9000000001', '1239000000001', '+19000000001', '9000000001<script>', '0000000000', '++919000000001']);
    }

    #[DataProvider('maliciousPhones')]
    public function testPhoneInputCannotBeTruncatedIntoAnotherAccount(string $phone): void
    {
        $this->expectException(InvalidArgumentException::class);
        OtpService::normalizePhone($phone);
    }

    public static function maliciousLinks(): array
    {
        return array_map(static fn ($value) => [$value], ['javascript:alert(1)', '//evil.test', '/%2f/evil.test', '/%5cevil.test', '/\\evil.test', '/%0aevil', 'data:text/html,<script>alert(1)</script>']);
    }

    #[DataProvider('maliciousLinks')]
    public function testCampaignDestinationsRejectExecutableAndAmbiguousLinks(string $url): void
    {
        $this->assertFalse((new CampaignLocationService())->safe($url));
    }

    public static function privateResponses(): array
    {
        return [['/admin/orders', 200], ['/api/v1/account/profile', 200], ['/api/v1/admin/settings', 401], ['/api/v1/admin/products', 403], ['/logout', 302], ['/login', 200]];
    }

    #[DataProvider('privateResponses')]
    public function testPrivateResponsesCannotRetainAPublicCachePolicy(string $path, int $status): void
    {
        $request = new IncomingRequest(config('App'), new URI('http://example.com' . $path), null, new UserAgent());
        $response = (new Response(config('App')))->setStatusCode($status)->setHeader('Cache-Control', 'public, max-age=300');
        (new PrivateResponseFilter())->after($request, $response);
        $this->assertSame('private, no-store, max-age=0', $response->getHeaderLine('Cache-Control'));
        $this->assertSame('no-cache', $response->getHeaderLine('Pragma'));
    }

    public function testGeneratedMediaRetainsPublicCacheButMissingMediaDoesNot(): void
    {
        $request = new IncomingRequest(config('App'), new URI('http://example.com/media/products/' . str_repeat('a', 48) . '.png'), null, new UserAgent());
        $response = (new Response(config('App')))->setContentType('image/png')->removeHeader('Cache-Control')->setHeader('Cache-Control', 'public, max-age=86400');
        (new PrivateResponseFilter())->after($request, $response);
        $this->assertSame('public, max-age=86400', $response->getHeaderLine('Cache-Control'));
        $response->setStatusCode(404);
        (new PrivateResponseFilter())->after($request, $response);
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testExecutableUploadCannotUseAnImageExtension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sw-image-boundary-');
        try {
            file_put_contents($path, '<?php echo "executable";');
            $this->expectException(InvalidArgumentException::class);
            (new ProductImageService())->inspect($path, filesize($path));
        } finally { unlink($path); }
    }
}
