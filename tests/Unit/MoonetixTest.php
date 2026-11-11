<?php

namespace Tests\Unit;

use App\Support\Moonetix;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MoonetixTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function vectors(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/moonetix-vectors.json'), true);
    }

    public static function linkCases(): array
    {
        $cases = [];
        foreach (self::vectors()['links'] as $userId => $link) {
            $cases['user '.$userId] = [(string) $userId, $link];
        }

        return $cases;
    }

    #[DataProvider('linkCases')]
    public function test_wall_link_matches_the_moonetix_link(string $userId, string $expected): void
    {
        $this->assertSame($expected, Moonetix::wallUrl($userId));
    }

    public function test_sub_ids_are_appended_and_invalid_ones_dropped(): void
    {
        $url = Moonetix::wallUrl('42', ['sub1' => 'earn_page', 'sub2' => 'bad value!', 'sub9' => 'x', 'sub3' => 'a~b']);

        $this->assertSame(self::vectors()['links']['42'].'&sub1=earn_page&sub3=a~b', $url);
    }

    public function test_user_ids_the_offerwall_rejects_throw(): void
    {
        $this->expectException(RuntimeException::class);
        Moonetix::wallUrl('user with spaces');
    }

    public function test_missing_salt_throws_instead_of_signing_with_an_empty_key(): void
    {
        config(['services.moonetix.link_salt' => '']);
        $this->expectExceptionMessage('MOONETIX_LINK_SALT is not set');
        Moonetix::wallUrl('1');
    }

    public function test_amounts_are_exact(): void
    {
        $this->assertSame(150000000, Moonetix::toMicro('150'));
        $this->assertSame(750000, Moonetix::toMicro('0.75'));
        $this->assertSame(1234560, Moonetix::toMicro('1.23456'));
        $this->assertNull(Moonetix::toMicro('-1'));
        $this->assertNull(Moonetix::toMicro('1e3'));
        $this->assertNull(Moonetix::toMicro('1.'));
        $this->assertNull(Moonetix::toMicro(''));
        $this->assertSame('151.98456', Moonetix::formatMicro(151984560));
        $this->assertSame('-0.25', Moonetix::formatMicro(-250000));
        $this->assertSame('0', Moonetix::formatMicro(0));
    }
}
