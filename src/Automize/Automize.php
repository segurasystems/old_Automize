<?php
namespace Zenderator\Automize;

use CLIOpts\CLIOpts;
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\CliMenuBuilder;
use PhpSchool\CliMenu\MenuItem\AsciiArtItem;
use PhpSchool\CliMenu\MenuItem\SelectableItem;
use Zenderator\Zenderator;

class Automize
{
    /** @var Zenderator|null */
    private $zenderator;
    /** @var string */
    private $sdkOutputPath;
    /** @var CliMenuBuilder */
    private $menu;
    /** @var string */
    private $automizeInstanceName;
    /** @var SelectableItem[] */
    private $applicationSpecificMenuItems;
    
    public function __construct(Zenderator $zenderator = null, $sdkOutputPath)
    {
        $this->zenderator    = $zenderator;
        $this->sdkOutputPath = $sdkOutputPath;

        $this->automizeInstanceName = 'Gone.io Automizer - ' . APP_NAME;
    }

    private function vpnCheck()
    {
        if (!$this->zenderator->vpnCheck()) {
            echo "WARNING! You're not connected to the VPN!\n";
            $this->zenderator->waitForKeypress();
        }
    }

    private function getApplicationSpecificMenuItems()
    {
        $commands = $this->getApplicationSpecificCommands();
        foreach ($commands as $command) {
            $item                                 = new SelectableItem($command->getCommandName(), [$command, "action"]);
            $this->applicationSpecificMenuItems[] = $item;
        }
    }

    /**
     * @return AutomizeCommandInterface[]
     */
    private function getApplicationSpecificCommands() : array
    {
        $commands         = [];
        $appNamespaceBits = explode("\\", APP_CORE_NAME);
        unset($appNamespaceBits[count($appNamespaceBits) - 1]);
        $appScopeBits                         = explode('\\', APP_CORE_NAME);
        $appScope                             = implode('\\', array_slice($appScopeBits, 0, -1));
        $applicationSpecificCommandsLocations =
        [
            $appScope        => APP_ROOT . "/src/Commands",
            'Gone\AppCore' => APPCORE_ROOT . "/src/Commands",
        ];

        foreach ($applicationSpecificCommandsLocations as $appNamespace => $applicationSpecificCommandsLocation) {
            if (file_exists($applicationSpecificCommandsLocation)) {
                foreach (new \DirectoryIterator($applicationSpecificCommandsLocation) as $file) {
                    $commandSuffix = "Command.php";
                    $offset        = strlen($commandSuffix);
                    if (!$file->isDot() && $file->getExtension() == "php" && substr($file->getFilename(), strlen($file->getFilename()) - $offset, $offset) == $commandSuffix) {
                        $class = $appNamespace . "\\Commands\\" . str_replace($commandSuffix, "", $file->getFilename()) . "Command";
                        /** @var AutomizeCommand $command */
                        $command = new $class($this->zenderator);
                        //\Kint::dump($command, $class, $file);exit;
                        $commands[$class] = $command;
                    }
                }
            }
        }

        ksort($commands);

        return $commands;
    }

    private function buildMenu()
    {
        $scope      = $this;
        $this->menu = new CliMenuBuilder();
        $this->menu->setBackgroundColour('red');
        $this->menu->setForegroundColour('white');
        $this->menu->setTitle($this->automizeInstanceName);
        $this->menu->addAsciiArt(file_get_contents(__DIR__ . "/../../assets/logo.ascii"), AsciiArtItem::POSITION_LEFT);
        $this->menu->addLineBreak('-');

        $this->menu->addItem('Run Zenderator', function (CliMenu $menu) use ($scope) {
            /** @var Automize $scope */
            $scope->zenderator
                ->makeZenderator(false)
                ->waitForKeypress();
            $menu->redraw();
        });
        $this->menu->addItem('Run SDKifier', function (CliMenu $menu) use ($scope) {
            /** @var Automize $scope */
            $scope->zenderator
                ->purgeSDK($scope->sdkOutputPath)
                ->checkGitSDK($scope->sdkOutputPath)
                ->makeSDK($scope->sdkOutputPath, false)
                ->runSDKTests($scope->sdkOutputPath)
                ->sendSDKToGit($scope->sdkOutputPath)
                ->waitForKeypress();
            $menu->redraw();
        });
        $this->menu->addItem('Purge System of Sin (Rebuild Everything & Clean)', function (CliMenu $menu) use ($scope) {
            /** @var Automize $scope */
            $scope->zenderator
                ->makeZenderator(false)
                ->makeSDK($scope->sdkOutputPath, false)
                ->cleanCode()
                ->runTests(false)
                ->waitForKeypress();
            $menu->redraw();
        });
        if (count($this->applicationSpecificMenuItems)) {
            $this->menu->addLineBreak('-');
            $customCommandsSubMenu = $this->menu->addSubMenu(APP_NAME . " Custom Commands");
            $customCommandsSubMenu->setTitle(APP_NAME . " Custom Commands");
            foreach ($this->applicationSpecificMenuItems as $menuItem) {
                $customCommandsSubMenu->addMenuItem($menuItem);
            }
            $customCommandsSubMenu->addLineBreak('-');
            $customCommandsSubMenu->end();
        }
        $this->menu->addLineBreak('-');
        $testSubMenu = $this->menu->addSubMenu('Tests');
        $testSubMenu->setTitle($this->automizeInstanceName . ' > Tests');
        $testSubMenu->addItem('Run Tests without Coverage (fast)', function (CliMenu $menu) use ($scope) {
            /** @var Automize $scope */
            $scope->zenderator
                ->runTests(false)
                ->waitForKeypress();
            $menu->redraw();
        });
        $testSubMenu->addItem('Run Tests with Coverage (slow)', function (CliMenu $menu) use ($scope) {
            /** @var Automize $scope */
            $scope->zenderator
                ->runTests(true)
                ->waitForKeypress();
            $menu->redraw();
        });
        $testSubMenu->addItem('Run Tests but Stop on Failure/Error', function (CliMenu $menu) use ($scope) {
            /** @var Automize $scope */
            $scope->zenderator
                ->runTests(true, true)
                ->waitForKeypress();
            $menu->redraw();
        });
        $testSubMenu->end();
        $this->menu->addLineBreak('-');
        $composerSubMenu = $this->menu->addSubMenu('Composer');
        $composerSubMenu->setTitle($this->automizeInstanceName . ' > Composer');
        $composerSubMenu->addItem('Rebuild Composer Autoloader', function (CliMenu $menu) use ($scope) {
            /** @var Automize $scope */
            $scope->zenderator
                ->cleanCodeComposerAutoloader()
                ->waitForKeypress();
            $menu->redraw();
        });
        $composerSubMenu->end();
        $this->menu->addItem('Run Clean', function (CliMenu $menu) use ($scope) {
            /** @var Automize $scope */
            $scope->zenderator
                ->cleanCode()
                ->waitForKeypress();
            $menu->redraw();
        });

        $this->menu->addLineBreak('-');
        $this->menu = $this->menu->build();
    }
    
    public function run()
    {
        $this->getApplicationSpecificMenuItems();
        #$this->vpnCheck();
        $values = $this->checkForArguments();
        if ($values->count()) {
            $this->runNonInteractive();
        } else {
            $this->runInteractive();
        }
    }

    private function runInteractive()
    {
        $this->buildMenu();
        $this->menu->open();
    }

    private function runNonInteractive()
    {
        if($this->zenderator) {
            $this->zenderator->disableWaitForKeypress();
        }
        $values = $this->checkForArguments();
        // non-interactive mode
        foreach ($values as $name => $value) {
            switch ($name) {
                case 'zenderator':
                    if(!$this->zenderator){
                        echo "Cannot run {$name}, Zenderator is not installed.\n";
                        break;
                    }
                    $this->zenderator->makeZenderator();
                    break;
                case 'clean':
                    if(!$this->zenderator){
                        echo "Cannot run {$name}, Zenderator is not installed.\n";
                        break;
                    }
                    $this->zenderator->cleanCodePHPCSFixer();
                    break;
                case 'composer-optimise':
                    if(!$this->zenderator){
                        echo "Cannot run {$name}, Zenderator is not installed.\n";
                        break;
                    }
                    $this->zenderator->cleanCodeComposerAutoloader();
                    break;
                case 'sdk':
                    if(!$this->zenderator){
                        echo "Cannot run {$name}, Zenderator is not installed.\n";
                        break;
                    }
                    $this->zenderator->runSdkifier($value);
                    break;
                case 'tests':
                case 'tests-coverage':
                case 'tests-debug':
                    if(!$this->zenderator){
                        echo "Cannot run {$name}, Zenderator is not installed.\n";
                        break;
                    }
                    $this->zenderator->runTests(
                        $values->offsetExists('tests-coverage'),
                        $values->offsetExists('tests-stop-on-error'),
                        $values->offsetExists('tests-suite') ? $values->offsetGet('tests-suite') : '',
                        $values->offsetExists('tests-debug')
                    );
                    break;
                case 'matt-mode':
                    if(!$this->zenderator){
                        echo "Cannot run {$name}, Zenderator is not installed.\n";
                        break;
                    }
                    $this->zenderator
                        ->makeZenderator()
                        ->cleanCodePHPCSFixer()
                        ->cleanCodeComposerAutoloader()
                        ->runTests(false, true);
                    break;
                case 'sleep':
                    echo "Sleeping for {$value} seconds...";
                    sleep($value);
                    echo " [DONE]\n";
                    break;
                default:
                    foreach ($this->getApplicationSpecificCommands() as $command) {
                        $flag = str_replace(" ", "-", strtolower($command->getCommandName()));
                        if ($flag == $name) {
                            echo "Running {$command->getCommandName()}...\n";
                            if ($values->offsetExists($flag)) {
                                $command->action();
                            }
                            echo "Completed running {$command->getCommandName()}\n\n";
                        }
                    }
            }
        }
    }

    private function checkForArguments()
    {
        $arguments = "
            Usage: {self} [options]
            -z --zenderator Run Zenderator
            -s --sdk <path> Run SDKifier
            -c --clean Run Cleaner
            -o --composer-optimise Optimise composer autoloader
            -t --tests Run tests
            -T --tests-coverage Run tests with coverage
            -x --tests-stop-on-error Stop tests on Errors or Failures
            --tests-debug run tests with debug flag
            --sleep <seconds> Sleep for time defined in seconds
            ";
        foreach ($this->getApplicationSpecificCommands() as $command) {
            $arguments.="--" . str_replace(" ", "-", strtolower($command->getCommandName())) . " Run {$command->getCommandName()}\n";
        }
        $arguments.="-M --matt-mode Shortcode for -zcotx\n";
        $arguments.="-h --help Show this help\n";
        $values = CLIOpts::run($arguments);

        return $values;
    }
}
