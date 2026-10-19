<?php

namespace App\Tests\Functional\Access;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class OpenDealershipCommandTest extends ApiTestCase
{
    /**
     * @param array<string, string> $input
     */
    private function open(array $input): CommandTester
    {
        $tester = new CommandTester(new Application(static::$kernel ?? static::bootKernel())->find('mustang:dealership:open'));
        $tester->execute($input, ['interactive' => false]);

        return $tester;
    }

    public function testItOpensADealershipWithItsFirstAdminWhoCanSignIn(): void
    {
        $tester = $this->open([
            'name' => 'Bay Ford',
            'email' => 'Owner@BayFord.example',
            '--first-name' => 'Dana',
            '--last-name' => 'Owens',
            '--timezone' => 'America/Chicago',
            '--password' => 'a-long-first-password',
        ]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $dealership = $this->db()->fetchAssociative("SELECT id, timezone FROM dealerships WHERE name = 'Bay Ford'");
        self::assertSame('America/Chicago', $dealership['timezone']);
        $admin = $this->db()->fetchAssociative("SELECT dealership_id, role, active FROM users WHERE email = 'owner@bayford.example'");
        self::assertSame([$dealership['id'], 'admin', 1], [$admin['dealership_id'], $admin['role'], (int) $admin['active']], 'The email is stored lower case.');
        self::assertStringNotContainsString('a-long-first-password', $tester->getDisplay(), 'A password given on the command line is never echoed.');

        $body = $this->request('POST', '/api/v1/auth/login', ['email' => 'owner@bayford.example', 'password' => 'a-long-first-password']);
        $this->assertStatus(200);
        self::assertSame('enroll', $body['next'], 'The new admin sets up two-step sign-in on the first sign-in.');
    }

    public function testWithoutAPasswordItMakesOneAndShowsItOnce(): void
    {
        $tester = $this->open(['name' => 'Gulf Toyota', 'email' => 'gm@gulftoyota.example', '--first-name' => 'Lee', '--last-name' => 'Ng']);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertMatchesRegularExpression('/Password: (\S{20,})/', $tester->getDisplay());
        preg_match('/Password: (\S+)/', $tester->getDisplay(), $match);
        $this->request('POST', '/api/v1/auth/login', ['email' => 'gm@gulftoyota.example', 'password' => $match[1]]);
        $this->assertStatus(200);
        self::assertSame('America/New_York', $this->db()->fetchOne("SELECT timezone FROM dealerships WHERE name = 'Gulf Toyota'"));
    }

    public function testAnEmailAlreadyInUseOpensNothing(): void
    {
        $before = (int) $this->db()->fetchOne('SELECT COUNT(*) FROM dealerships');

        $tester = $this->open(['name' => 'Twice Motors', 'email' => 'admin@mustang.test', '--first-name' => 'A', '--last-name' => 'B']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('already', $tester->getDisplay());
        self::assertSame($before, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM dealerships'), 'No dealership without its admin.');
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function badInput(): iterable
    {
        yield 'unknown time zone' => [['--timezone' => 'Mars/Olympus'], 'time zone'];
        yield 'short password' => [['--password' => 'short'], '12 characters'];
        yield 'not an email' => [['email' => 'nobody'], 'email'];
        yield 'no last name' => [['--last-name' => ''], 'name'];
    }

    /**
     * @param array<string, string> $override
     */
    #[DataProvider('badInput')]
    public function testBadInputIsRefusedWithTheReason(array $override, string $reason): void
    {
        $tester = $this->open($override + ['name' => 'Bad Input Motors', 'email' => 'x@badinput.example', '--first-name' => 'A', '--last-name' => 'B']);

        self::assertNotSame(0, $tester->getStatusCode());
        self::assertStringContainsStringIgnoringCase($reason, $tester->getDisplay());
        self::assertFalse($this->db()->fetchOne("SELECT id FROM dealerships WHERE name = 'Bad Input Motors'"));
    }
}
