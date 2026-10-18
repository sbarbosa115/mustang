<?php

namespace App\Tests\Functional\Messaging;

use App\Tests\Support\ApiTestCase;

final class TemplateApiTest extends ApiTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function create(string $name = 'Test text', string $channel = 'text', string $body = 'Hi {first_name}, it is {rep_name} from {dealership}.', ?string $subject = null): array
    {
        $payload = ['name' => $name, 'channel' => $channel, 'body' => $body];
        if (null !== $subject) {
            $payload['subject'] = $subject;
        }

        return $this->request('POST', '/api/v1/templates', $payload);
    }

    public function testAManagerCreatesReadsChangesAndDeletesATemplate(): void
    {
        $this->signInAs('manager');
        $created = $this->create('Follow up');
        $this->assertStatus(201);
        self::assertSame('Follow up', $created['name']);
        self::assertSame('text', $created['channel']);
        self::assertTrue($created['active'], 'A new template is active.');
        self::assertNull($created['subject']);

        $one = $this->request('GET', '/api/v1/templates/'.$created['id']);
        $this->assertStatus(200);
        self::assertSame($created['id'], $one['id']);

        $changed = $this->request('PATCH', '/api/v1/templates/'.$created['id'], ['name' => 'Follow up v2', 'body' => 'Hello {first_name}', 'active' => false]);
        $this->assertStatus(200);
        self::assertSame('Follow up v2', $changed['name']);
        self::assertSame('Hello {first_name}', $changed['body']);
        self::assertFalse($changed['active']);

        $this->request('DELETE', '/api/v1/templates/'.$created['id']);
        $this->assertStatus(204);
        $this->request('GET', '/api/v1/templates/'.$created['id']);
        $this->assertStatus(404, 'A deleted template is gone.');
    }

    public function testEveryoneCanReadButOnlyAManagerWrites(): void
    {
        $this->signInAs('manager');
        $template = $this->create('Readable');

        foreach (['sales', 'bdc'] as $role) {
            $this->signInAs($role);
            $list = $this->request('GET', '/api/v1/templates');
            $this->assertStatus(200, $role.' can list templates for the composer.');
            self::assertContains('Readable', array_column($list['items'], 'name'));
            $this->request('GET', '/api/v1/templates/'.$template['id']);
            $this->assertStatus(200);

            $this->request('POST', '/api/v1/templates', ['name' => 'Nope', 'channel' => 'text', 'body' => 'Hi']);
            $this->assertStatus(403, $role.' cannot create.');
            $this->request('PATCH', '/api/v1/templates/'.$template['id'], ['name' => 'Nope']);
            $this->assertStatus(403, $role.' cannot change.');
            $this->request('DELETE', '/api/v1/templates/'.$template['id']);
            $this->assertStatus(403, $role.' cannot delete.');
        }
    }

    public function testTheListFiltersByChannelAndActiveAndIsOrderedByName(): void
    {
        $this->signInAs('manager');
        $this->create('Zeta text');
        $this->create('Alpha email', 'email', 'Body', 'Subject');
        $off = $this->create('Beta off');
        $this->request('PATCH', '/api/v1/templates/'.$off['id'], ['active' => false]);

        $emails = $this->request('GET', '/api/v1/templates?channel=email');
        self::assertContains('Alpha email', array_column($emails['items'], 'name'));
        self::assertSame([], array_filter($emails['items'], static fn (array $t): bool => 'email' !== $t['channel']), 'Only email templates.');

        $active = $this->request('GET', '/api/v1/templates?active=1');
        self::assertNotContains('Beta off', array_column($active['items'], 'name'));
        $inactive = $this->request('GET', '/api/v1/templates?active=0');
        self::assertSame(['Beta off'], array_values(array_intersect(array_column($inactive['items'], 'name'), ['Beta off', 'Zeta text'])));

        $names = array_column($this->request('GET', '/api/v1/templates')['items'], 'name');
        $sorted = $names;
        sort($sorted, \SORT_NATURAL | \SORT_FLAG_CASE);
        self::assertSame($sorted, $names, 'Templates come by name.');
    }

    public function testAnUnknownPlaceholderIsRefusedInTheBodyAndTheSubject(): void
    {
        $this->signInAs('manager');
        $body = $this->create('Bad', 'text', 'Hi {first_name}, your {pet_name} is cute');
        $this->assertError(422, 'unknown_placeholder', $body);
        self::assertSame('body', $body['detail']['field']);
        self::assertSame(['pet_name'], $body['detail']['placeholders']);

        $subject = $this->create('Bad email', 'email', 'Fine {first_name}', 'About {car}');
        $this->assertError(422, 'unknown_placeholder', $subject);
        self::assertSame('subject', $subject['detail']['field']);

        $ok = $this->create('Good', 'text', 'All six: {first_name} {last_name} {rep_name} {dealership} {vehicle} {appointment_time}');
        $this->assertStatus(201);
        self::assertSame('Good', $ok['name']);

        $patch = $this->request('PATCH', '/api/v1/templates/'.$ok['id'], ['body' => 'Hi {nickname}']);
        $this->assertError(422, 'unknown_placeholder', $patch, 'A change is checked too.');
    }

    public function testAnEmailNeedsASubjectAndATextTemplateKeepsNone(): void
    {
        $this->signInAs('manager');
        $missing = $this->create('No subject', 'email', 'Body');
        $this->assertError(422, 'subject_required', $missing);
        self::assertSame('subject', $missing['detail']['field']);

        $text = $this->create('Text with subject', 'text', 'Body', 'Ignored');
        $this->assertStatus(201);
        self::assertNull($text['subject'], 'Texts have no subject.');

        $email = $this->create('Email ok', 'email', 'Body', 'Your visit');
        self::assertSame('Your visit', $email['subject']);
        $cleared = $this->request('PATCH', '/api/v1/templates/'.$email['id'], ['subject' => '']);
        $this->assertError(422, 'subject_required', $cleared, 'Clearing the subject of an email is refused.');
    }

    public function testShapeIsValidatedAndACallIsNotATemplateChannel(): void
    {
        $this->signInAs('manager');
        $this->request('POST', '/api/v1/templates', ['channel' => 'text', 'body' => 'x']);
        $this->assertStatus(422, 'A name is required.');
        $this->request('POST', '/api/v1/templates', ['name' => 'x', 'channel' => 'text']);
        $this->assertStatus(422, 'A body is required.');
        $call = $this->create('Call', 'call');
        $this->assertError(422, 'template_channel_invalid', $call);
    }

    public function testAnotherDealershipSeesNoTemplatesAndGets404(): void
    {
        $this->signInAs('manager');
        $template = $this->create('Mine only');

        $this->assertNotFoundForOtherDealership('GET', '/api/v1/templates/'.$template['id']);
        $this->assertNotFoundForOtherDealership('PATCH', '/api/v1/templates/'.$template['id'], ['name' => 'Stolen']);
        $this->assertNotFoundForOtherDealership('DELETE', '/api/v1/templates/'.$template['id']);

        $list = $this->request('GET', '/api/v1/templates');
        self::assertNotContains('Mine only', array_column($list['items'], 'name'));
        $this->signInAs('manager');
        $this->request('GET', '/api/v1/templates/'.$template['id']);
        $this->assertStatus(200, 'The other dealership changed nothing.');
    }

    public function testTheDemoSeedHasTheThreeStarterTemplates(): void
    {
        $this->signInAs('sales');
        $names = array_column($this->request('GET', '/api/v1/templates')['items'], 'name');
        foreach (['Appointment confirmation', 'First reply', 'Vehicle match'] as $name) {
            self::assertContains($name, $names);
        }
    }
}
