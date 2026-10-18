<?php

namespace App\Customer\UI\Cli;

use App\Customer\Domain\Model\ContactKind;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Model\CustomerContact;
use App\Customer\Domain\Model\CustomerOwnedVehicle;
use App\Shared\Domain\Channel;
use App\Shared\UI\Cli\DemoSeeder;
use App\Shared\UI\Cli\SeedContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * 30 customers for Mustang Motors and 5 for Other Motors, deterministic so tests and the smoke suite can name them.
 * Keys: customer.<n> (1-30, Mustang) and customer.other.<n> (1-5).
 */
final readonly class CustomerSeeder implements DemoSeeder
{
    private const array FIRST = ['Sarah', 'Michael', 'Jessica', 'David', 'Ashley', 'Chris', 'Amanda', 'James', 'Emily', 'Robert',
        'Lauren', 'Daniel', 'Megan', 'Kevin', 'Rachel', 'Brian', 'Nicole', 'Jason', 'Stephanie', 'Ryan',
        'Carlos', 'Ana', 'Luis', 'Sofia', 'Diego', 'Valentina', 'Mateo', 'Camila', 'Javier', 'Isabella'];
    private const array LAST = ['Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez',
        'Lopez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez',
        'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker', 'Young'];
    private const array OWNED = [[2019, 'Toyota', 'Camry'], [2017, 'Honda', 'Civic'], [2020, 'Ford', 'F-150'], [2018, 'Nissan', 'Rogue'], [2021, 'Chevrolet', 'Equinox']];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public static function priority(): int
    {
        return 900;
    }

    public function seed(SeedContext $context): void
    {
        $dealership = $context->id('dealership.mustang');
        $reps = [$context->id('user.sales@mustang.test'), $context->id('user.sales2@mustang.test')];
        $bdc = $context->id('user.bdc@mustang.test');

        for ($i = 1; $i <= 30; ++$i) {
            $first = self::FIRST[$i - 1];
            $last = self::LAST[$i - 1];
            $customer = new Customer(
                $dealership,
                $first,
                $last,
                preferredChannel: 0 === $i % 4 ? Channel::Email : Channel::Text,
                sales1UserId: $reps[$i % 2],
                bdcUserId: 0 === $i % 3 ? $bdc : null,
                source: 0 === $i % 5 ? 'walk_in' : 'website',
            );
            $customer->contacts()->add(new CustomerContact($dealership, $customer, ContactKind::Phone, \sprintf('+1813555%04d', 1000 + $i), 'mobile', true));
            $customer->contacts()->add(new CustomerContact($dealership, $customer, ContactKind::Email, strtolower(\sprintf('%s.%s@example.com', $first, $last)), null, true));
            if (0 === $i % 3) {
                [$year, $make, $model] = self::OWNED[$i % \count(self::OWNED)];
                $customer->ownedVehicles()->add(new CustomerOwnedVehicle($dealership, $customer, $year, $make, $model, purchasedAt: $context->now->modify(\sprintf('-%d months', 24 + $i))));
            }
            $this->em->persist($customer);
            $context->set('customer.'.$i, $customer->id());
        }

        $other = $context->id('dealership.other');
        for ($i = 1; $i <= 5; ++$i) {
            $customer = new Customer($other, 'Other'.$i, 'Customer', sales1UserId: $context->id('user.other@mustang.test'));
            $customer->contacts()->add(new CustomerContact($other, $customer, ContactKind::Phone, \sprintf('+1727555%04d', 2000 + $i), 'mobile', true));
            $this->em->persist($customer);
            $context->set('customer.other.'.$i, $customer->id());
        }
    }
}
