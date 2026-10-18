<?php

namespace App\Access\Infrastructure\Totp;

use App\Access\Application\Port\Totp;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use OTPHP\TOTP as OtpTotp;
use Psr\Clock\ClockInterface;

/**
 * RFC 6238 with the defaults every authenticator app uses: SHA-1, six digits, 30-second steps.
 */
final readonly class OtphpTotp implements Totp
{
    private const string ISSUER = 'Mustang';
    /** Now first, then one step of clock drift either side, so a code is booked against its most likely step. */
    private const array STEP_OFFSETS = [0, -1, 1];

    public function __construct(private ClockInterface $clock)
    {
    }

    public function newSecret(): string
    {
        return OtpTotp::generate($this->clock)->getSecret();
    }

    public function provisioningUri(string $secret, string $account): string
    {
        $totp = $this->totp($secret);
        $totp->setLabel($account);
        $totp->setIssuer(self::ISSUER);

        return $totp->getProvisioningUri();
    }

    public function qrSvg(string $uri): string
    {
        return new Writer(new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd()))->writeString($uri);
    }

    public function matchingStep(string $secret, string $code, \DateTimeImmutable $at): ?int
    {
        if (1 !== preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $totp = $this->totp($secret);
        $period = $totp->getPeriod();
        $now = $at->getTimestamp();
        foreach (self::STEP_OFFSETS as $offset) {
            $timestamp = $now + $offset * $period;
            if ($timestamp >= 0 && hash_equals($totp->at($timestamp), $code)) {
                return intdiv($timestamp, $period);
            }
        }

        return null;
    }

    private function totp(string $secret): OtpTotp
    {
        return OtpTotp::createFromSecret($secret, $this->clock);
    }
}
