<?php
/**
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AspendoraSiteAudit\Commands;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AspendoraSiteAudit\Analyzer;
use Piwik\Plugins\AspendoraSiteAudit\Auditor;

class RunAudit extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('aspendora-audit:run');
        $this->setDescription('Crawl and audit every site in ASPENDORA_AUDIT_SITE_MAP now (the weekly site audit)');
        $this->addRequiredValueOption('idsite', null, 'Audit only this idSite');
        $this->addRequiredValueOption('max-pages', null, 'Crawl limit per site (default: ASPENDORA_AUDIT_MAX_PAGES or 2000)');
        $this->addNoValueOption('force', null, 'Run even if another audit of the site is marked running (started < 2 h ago)');
    }

    protected function doExecute(): int
    {
        $input = $this->getInput();
        $output = $this->getOutput();
        $idSite = $input->getOption('idsite');
        $maxPages = $input->getOption('max-pages');
        $auditor = new Auditor(StaticContainer::get(LoggerInterface::class), function ($msg) use ($output) {
            $output->writeln("  $msg");
        });
        $results = $auditor->runAll(
            $idSite !== null ? (int) $idSite : null,
            $maxPages !== null ? (int) $maxPages : null,
            false,
            (bool) $input->getOption('force')
        );
        if (!$results) {
            $output->writeln('<comment>No sites configured (ASPENDORA_AUDIT_SITE_MAP is empty)</comment>');
            return self::SUCCESS;
        }
        $failed = false;
        foreach ($results as $r) {
            $head = sprintf('site %d (%s): ', $r['idsite'], $r['start_url']);
            if ($r['status'] === 'skipped') {
                $output->writeln("<comment>{$head}skipped — {$r['reason']}</comment>");
                continue;
            }
            if ($r['status'] === 'failed') {
                $failed = true;
                $output->writeln("<error>{$head}run {$r['idrun']} FAILED — {$r['reason']}</error>");
                continue;
            }
            $c = $r['counts'];
            $output->writeln(sprintf('<info>%srun %d — %d URLs in %ss%s; %d errors, %d warnings, %d notices</info>',
                $head, $r['idrun'], $r['pages'], $r['duration_s'], $r['truncated'] ? ' (stopped at max pages)' : '',
                $c['error'], $c['warning'], $c['notice']));
            foreach (array_slice($r['by_code'], 0, 12, true) as $code => $n) {
                $output->writeln(sprintf('    %-8s %-26s %d URL(s)', Analyzer::ISSUES[$code][0], $code, $n));
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
