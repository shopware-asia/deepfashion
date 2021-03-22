<?php declare(strict_types=1);

namespace DeepFashion\Content\DeepFashionSeeder;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DeepFashionCommand extends Command
{
    protected static $defaultName = 'deepfashion:demodata';

    /**
     * @var DeepFashionSeeder
     */
    private $deepFashionSeeder;

    /**
     * @var string
     */
    private $kernelEnv;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        DeepFashionSeeder $deepFashionSeeder,
        EventDispatcherInterface $eventDispatcher,
        string $kernelEnv
    ) {
        parent::__construct();

        $this->kernelEnv = $kernelEnv;
        $this->deepFashionSeeder = $deepFashionSeeder;
        $this->eventDispatcher = $eventDispatcher;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $io->title('Deep Fashion Demodata Generator');

        $context = Context::createDefaultContext();

        $request = new DemodataRequest();

        $demoContext = $this->deepFashionSeeder->generate($request, $context, $io);

        $io->table(
            ['Entity', 'Items', 'Time'],
            $demoContext->getTimings()
        );

        return 0;
    }
}
