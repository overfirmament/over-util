<?php

namespace Overfirmament\OverUtils\Tests;

use Overfirmament\OverUtils\ToolBox\RabbitMqConnectionManager;
use PHPUnit\Framework\Attributes\Test;

class RabbitMqConnectionManagerTest extends TestCase
{
    #[Test]
    public function connection_ids_are_stable_until_the_connection_is_closed(): void
    {
        $manager = new RabbitMqConnectionManager;

        $id = $manager->connectionId('default');

        $this->assertSame($id, $manager->connectionId('default'));

        $manager->disconnect('default');

        $this->assertNotSame($id, $manager->connectionId('default'));
    }

    #[Test]
    public function each_connection_name_has_its_own_id(): void
    {
        $manager = new RabbitMqConnectionManager;

        $this->assertNotSame(
            $manager->connectionId('first'),
            $manager->connectionId('second')
        );
    }

    #[Test]
    public function disconnecting_everything_clears_all_ids(): void
    {
        $manager = new RabbitMqConnectionManager;

        $first = $manager->connectionId('first');
        $second = $manager->connectionId('second');

        $manager->disconnect();

        $this->assertNotSame($first, $manager->connectionId('first'));
        $this->assertNotSame($second, $manager->connectionId('second'));
    }
}
