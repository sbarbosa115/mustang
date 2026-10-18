<?php

namespace App\Messaging\UI\Cli;

use App\Messaging\Domain\Model\MessageTemplate;
use App\Shared\Domain\Channel;
use App\Shared\UI\Cli\DemoSeeder;
use App\Shared\UI\Cli\SeedContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Three starter templates for Mustang Motors, written by the manager. Keys: template.confirmation,
 * template.first_reply, template.vehicle_match.
 */
final readonly class TemplateSeeder implements DemoSeeder
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public static function priority(): int
    {
        return 650;
    }

    public function seed(SeedContext $context): void
    {
        $dealership = $context->id('dealership.mustang');
        $manager = $context->id('user.manager@mustang.test');
        $templates = [
            'confirmation' => ['Appointment confirmation', Channel::Text, null, 'Hi {first_name}, this is {rep_name} from {dealership}. You are confirmed for {appointment_time}. Reply here if anything changes.'],
            'first_reply' => ['First reply', Channel::Text, null, 'Hi {first_name}, this is {rep_name} at {dealership}. Thanks for reaching out. When is a good time to talk?'],
            'vehicle_match' => ['Vehicle match', Channel::Email, 'A {vehicle} you may like', "Hi {first_name},\n\nWe have a {vehicle} that matches what you asked about. Would you like to see it?\n\n{rep_name}\n{dealership}"],
        ];
        foreach ($templates as $key => [$name, $channel, $subject, $body]) {
            $template = MessageTemplate::compose($dealership, $name, $channel, $body, $manager, $subject);
            $this->em->persist($template);
            $context->set('template.'.$key, $template->id());
        }
    }
}
