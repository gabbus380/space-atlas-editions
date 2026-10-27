<?php

declare(strict_types=1);

/**
 * Deterministic preparation and validation for the ATLAS country/ecosystem builders.
 *
 * Commands:
 *   php claude/atlas/tools/builders.php initialize
 *   php claude/atlas/tools/builders.php validate [all|country|ecosystem]
 *   php claude/atlas/tools/builders.php queue <country|ecosystem> [limit]
 *   php claude/atlas/tools/builders.php campaign country [target] [wave-size]
 *   php claude/atlas/tools/builders.php audit country
 *   php claude/atlas/tools/builders.php fetch <https://public.example/page>
 *   php claude/atlas/tools/builders.php protect-country <atlas.json> [source-ref]
 *   php claude/atlas/tools/builders.php self-test
 */

const ROOT = __DIR__.'/..';
const COUNTRY_DIR = ROOT.'/country-data';
const ECOSYSTEM_DIR = ROOT.'/ecosystem';
const REGISTRY_FILE = ROOT.'/registry/countries.json';
const AUTHORITATIVE_COUNTRY_FILE = COUNTRY_DIR.'/authoritative.json';
const AUTHORITATIVE_COUNTRY_COUNT = 62;
const AUTHORITATIVE_COUNTRY_SHA256 = 'c9856d45ad20d664da06747f39ffb92797da998b2914dd1434a4510a7ba55b98';
const FETCH_SCHEMA = 2;

const COUNTRY_STATUSES = ['unstarted', 'seeded', 'partial', 'complete', 'inherited'];
const ECOSYSTEM_STATUSES = ['unstarted', 'partial', 'mapped', 'inherited', 'none_found'];
const ECOSYSTEM_DEPTH_STATUSES = ['not_started', 'in_progress', 'complete', 'not_applicable'];
const MEDIA_KINDS = ['space_agency', 'space_office', 'government', 'national_press', 'space_press', 'science', 'business', 'broadcast'];
const ORG_TYPES = ['Company', 'University', 'Institute', 'Agency', 'Program', 'Cluster', null];

main($argv);

function main(array $argv): void
{
    $command = $argv[1] ?? 'help';

    try {
        match ($command) {
            'initialize' => initialize(),
            'validate' => validate($argv[2] ?? 'all'),
            'queue' => queue($argv[2] ?? '', isset($argv[3]) ? (int) $argv[3] : null),
            'campaign' => campaign($argv[2] ?? '', isset($argv[3]) ? (int) $argv[3] : 118, isset($argv[4]) ? (int) $argv[4] : 18),
            'audit' => audit($argv[2] ?? ''),
            'fetch' => fetchSource($argv[2] ?? ''),
            'protect-country' => protectCountry($argv[2] ?? '', $argv[3] ?? null),
            'self-test' => selfTest(),
            default => usage($command !== 'help'),
        };
    } catch (Throwable $exception) {
        fwrite(STDERR, 'ERROR: '.$exception->getMessage().PHP_EOL);
        exit(1);
    }
}

function usage(bool $error = false): never
{
    $text = <<<'TXT'
ATLAS worldwide-builder utility

  initialize
      Expand both 235-entry coverage ledgers, create an empty country database
      when absent, and migrate the old ecosystem packet to schema 1.

  validate [all|country|ecosystem]
      Validate the cumulative databases and coverage ledgers.

  queue <country|ecosystem> [limit]
      Emit a deterministic, budget-sized JSON work queue. Defaults: 12 country
      files or 18 ecosystem breadth entries.

  campaign country [target] [wave-size]
      Emit one deterministic campaign plan split into internal checkpoint waves.
      Defaults to 118 countries in waves of 18.

  audit country
      Run the publication-safety gate over cumulative country files. Partial
      files may retain declared gaps; complete files fail on any audit issue.

  fetch <public http(s) URL>
      Fetch a page directly, then retry through the Jina text reader when the
      origin blocks automation. Successful packets are cached by URL so later
      agents do not fetch the same page again.

  protect-country <atlas.json> [source-ref]
      Import every production country file marked verified as an immutable
      authoritative core. Campaign replacements become review-only research,
      while additive media candidates and their evidence remain available.

  self-test
      Exercise source decoding, error rejection and PDF safety checks.
TXT;

    fwrite($error ? STDERR : STDOUT, $text.PHP_EOL);
    exit($error ? 1 : 0);
}

function initialize(): void
{
    $registry = registry();
    $today = gmdate('Y-m-d');

    initializeCountry($registry, $today);
    initializeEcosystem($registry, $today);
    validate('all');
}

function initializeCountry(array $registry, string $today): void
{
    $baseline = readJson(COUNTRY_DIR.'/baseline.json');
    $databasePath = COUNTRY_DIR.'/database.json';
    $previousCoverage = file_exists(COUNTRY_DIR.'/coverage.json')
        ? (readJson(COUNTRY_DIR.'/coverage.json')['countries'] ?? [])
        : [];

    if (! file_exists($databasePath)) {
        writeJson($databasePath, [
            'schema' => 1,
            'asOf' => $today,
            'source' => 'ATLAS Vice President of Data Analytics',
            'registry' => 'claude/atlas/registry/countries.json',
            'baseline' => 'claude/atlas/country-data/baseline.json',
            'mode' => 'worldwide_build',
            'run' => ['searches_used' => 0, 'fetches_used' => 0, 'stopped_because' => null],
            'files' => new stdClass(),
            'evidence' => new stdClass(),
            'flags' => new stdClass(),
            'rejected' => new stdClass(),
        ]);
    }

    $database = readJson($databasePath);
    $files = $database['files'] ?? [];
    $seeded = $baseline['countries'] ?? [];
    $protected = file_exists(AUTHORITATIVE_COUNTRY_FILE)
        ? (readJson(AUTHORITATIVE_COUNTRY_FILE)['countries'] ?? [])
        : [];
    $entries = [];

    foreach ($registry as $iso3 => $country) {
        if (isset($files[$iso3])) {
            $verify = $files[$iso3]['verify'] ?? 'partial';
            $status = $verify === 'verified' ? 'complete' : 'partial';
            $source = 'database';
        } elseif (($previousCoverage[$iso3]['status'] ?? null) === 'inherited') {
            $status = 'inherited';
            $source = $previousCoverage[$iso3]['source'] ?? 'database';
        } elseif (isset($seeded[$iso3])) {
            $status = 'seeded';
            $source = 'baseline';
        } else {
            $status = 'unstarted';
            $source = null;
        }

        $isProtected = isset($protected[$iso3]);
        $entries[$iso3] = [
            'iso3' => $iso3,
            'status' => $status,
            'last_checked' => $status === 'unstarted'
                ? null
                : ($files[$iso3]['asOf'] ?? $previousCoverage[$iso3]['last_checked'] ?? null),
            'missing' => $isProtected
                ? ['review additive media extension and any proposed core changes']
                : (($previousCoverage[$iso3]['status'] ?? null) === $status
                ? ($previousCoverage[$iso3]['missing'] ?? countryMissing($status))
                : countryMissing($status)),
            'source' => $isProtected ? 'authoritative' : $source,
            'core_status' => $isProtected ? 'production_verified' : ($files[$iso3]['verify'] ?? 'partial'),
            'extension_last_checked' => $isProtected
                ? ($database['extensions'][$iso3]['asOf'] ?? null)
                : null,
            'extension_status' => $isProtected
                ? (($database['extensions'][$iso3]['status'] ?? null) === 'accepted' ? 'complete' : 'review_required')
                : 'not_applicable',
            'campaign_status' => $isProtected
                ? ($database['research_files'][$iso3]['verify'] ?? null)
                : ($files[$iso3]['verify'] ?? null),
        ];
    }

    writeJson(COUNTRY_DIR.'/coverage.json', [
        'schema' => 1,
        'asOf' => $today,
        'mode' => 'worldwide_build',
        'total_map_entries' => count($registry),
        'counts' => countsByStatus($entries, COUNTRY_STATUSES),
        'protected_production_files' => count($protected),
        'monthly_ready' => countryMonthlyReady($entries),
        'countries' => $entries,
    ]);
}

function countryMissing(string $status): array
{
    return match ($status) {
        'complete', 'inherited' => [],
        'seeded' => ['current verification', 'proved absences', 'media directory', 'claim evidence'],
        'partial' => ['see cumulative database flags and evidence'],
        default => ['government', 'strategy and law', 'stakeholders', 'media directory', 'claim evidence'],
    };
}

function initializeEcosystem(array $registry, string $today): void
{
    $baseline = readJson(ECOSYSTEM_DIR.'/baseline.json');
    $databasePath = ECOSYSTEM_DIR.'/database.json';
    $database = readJson($databasePath);

    if (isset($database['countries'])) {
        $database = migrateOldEcosystemDatabase($database, $today);
    }

    $previousCoverage = $database['coverage'] ?? [];
    $upserts = [];
    foreach ($database['upserts'] ?? [] as $organization) {
        $upserts[$organization['id']] = $organization;
    }

    $baselineByCountry = [];
    $knownIds = [];
    foreach ($baseline['organizations'] ?? [] as $organization) {
        $baselineByCountry[$organization['iso3']][] = $organization['id'];
        $knownIds[$organization['id']] = true;
    }

    $upsertsByCountry = [];
    foreach ($upserts as $organization) {
        $upsertsByCountry[$organization['iso3']][] = $organization['id'];
        $knownIds[$organization['id']] = true;
    }

    $coverage = [];
    foreach ($registry as $iso3 => $country) {
        $organizationIds = array_values(array_unique(array_merge(
            $baselineByCountry[$iso3] ?? [],
            $upsertsByCountry[$iso3] ?? [],
            $previousCoverage[$iso3]['organization_ids'] ?? [],
        )));
        sort($organizationIds);
        $hasRecords = $organizationIds !== [];
        $previous = $previousCoverage[$iso3] ?? [];
        $previousStatus = $previous['status'] ?? null;
        $status = in_array($previousStatus, ECOSYSTEM_STATUSES, true) && $previousStatus !== 'unstarted'
            ? $previousStatus
            : ($hasRecords ? 'partial' : 'unstarted');

        $coverage[$iso3] = [
            'iso3' => $iso3,
            'status' => $status,
            'depth_status' => $previous['depth_status']
                ?? (in_array($status, ['inherited', 'none_found'], true) ? 'not_applicable' : 'not_started'),
            'organization_ids' => $organizationIds,
            'directories_checked' => $previous['directories_checked'] ?? [],
            'missing' => $previous['missing'] ?? ($hasRecords
                ? ['breadth verification', 'national directories']
                : ['official lead body', 'national-language ecosystem search', 'national directories']),
            'proof' => $previous['proof'] ?? new stdClass(),
            'last_checked' => $previous['last_checked'] ?? null,
        ];
    }

    $database['schema'] = 1;
    $database['asOf'] = $today;
    $database['source'] = 'ATLAS Vice President of Ecosystems';
    $database['registry'] = 'claude/atlas/registry/countries.json';
    $database['baseline'] = 'claude/atlas/ecosystem/baseline.json';
    $database['mode'] = 'worldwide_breadth';
    $database['coverage'] = $coverage;
    unset($database['month'], $database['caps'], $database['countries']);
    writeJson($databasePath, orderedEcosystemDatabase($database));

    writeJson(ECOSYSTEM_DIR.'/coverage.json', [
        'schema' => 1,
        'asOf' => $today,
        'mode' => 'worldwide_breadth',
        'total_map_entries' => count($registry),
        'counts' => [
            'breadth_complete' => count(array_filter(
                $coverage,
                fn (array $entry): bool => in_array($entry['status'], ['mapped', 'inherited', 'none_found'], true),
            )),
            'depth_complete' => count(array_filter($coverage, fn (array $entry): bool => $entry['depth_status'] === 'complete')),
            'partial' => count(array_filter($coverage, fn (array $entry): bool => $entry['status'] === 'partial')),
            'unstarted' => count(array_filter($coverage, fn (array $entry): bool => $entry['status'] === 'unstarted')),
            'baseline_organizations' => count($baseline['organizations'] ?? []),
            'staged_upserts' => count($database['upserts'] ?? []),
        ],
        'countries' => array_map(
            fn (array $entry): array => [
                'iso3' => $entry['iso3'],
                'status' => $entry['status'],
                'depth_status' => $entry['depth_status'],
                'organization_count' => count($entry['organization_ids']),
                'remaining' => $entry['missing'],
                'last_checked' => $entry['last_checked'],
                'source_packet' => null,
            ],
            $coverage,
        ),
    ]);
}

function migrateOldEcosystemDatabase(array $old, string $today): array
{
    $upserts = [];
    $evidence = [];

    foreach ($old['countries'] as $packet) {
        foreach ($packet['organizations'] ?? [] as $change) {
            $organization = $change['organization'] ?? null;
            if (! is_array($organization) || ! isset($organization['id'])) {
                continue;
            }

            $sourceUrl = $organization['url'] ?? $organization['source'] ?? null;
            $organization['source'] = is_string($sourceUrl) && filter_var($sourceUrl, FILTER_VALIDATE_URL)
                ? $sourceUrl
                : null;
            unset($organization['url']);

            $upserts[$organization['id']] = array_intersect_key($organization, array_flip([
                'id', 'iso3', 'name', 'tier', 'category', 'type', 'desc', 'domains', 'conf', 'source', 'added',
            ]));
            $evidence[$organization['id']] = $change['proof'] ?? [];
        }
    }

    ksort($upserts);
    ksort($evidence);

    return [
        'schema' => 1,
        'asOf' => $today,
        'source' => 'ATLAS Vice President of Ecosystems',
        'registry' => 'claude/atlas/registry/countries.json',
        'baseline' => 'claude/atlas/ecosystem/baseline.json',
        'mode' => 'worldwide_breadth',
        'run' => [
            'searches_used' => (int) ($old['caps']['searches_used'] ?? 0),
            'fetches_used' => (int) ($old['caps']['fetches_used'] ?? 0),
            'stopped_because' => $old['caps']['stopped_because'] ?? null,
            'migration_note' => 'Migrated from the 2026-10-26 change-led packet; all represented countries remain partial.',
        ],
        'upserts' => array_values($upserts),
        'removals' => [],
        'evidence' => $evidence,
        'coverage' => new stdClass(),
    ];
}

function orderedEcosystemDatabase(array $database): array
{
    return [
        'schema' => $database['schema'],
        'asOf' => $database['asOf'],
        'source' => $database['source'],
        'registry' => $database['registry'],
        'baseline' => $database['baseline'],
        'mode' => $database['mode'],
        'run' => $database['run'] ?? ['searches_used' => 0, 'fetches_used' => 0, 'stopped_because' => null],
        'upserts' => array_values($database['upserts'] ?? []),
        'removals' => array_values($database['removals'] ?? []),
        'evidence' => $database['evidence'] ?? new stdClass(),
        'coverage' => $database['coverage'] ?? new stdClass(),
    ];
}

function queue(string $cause, ?int $limit): void
{
    $registry = registry();
    $cause = strtolower($cause);

    if (! in_array($cause, ['country', 'ecosystem'], true)) {
        throw new InvalidArgumentException('Queue cause must be country or ecosystem.');
    }

    $coverageFile = $cause === 'country' ? COUNTRY_DIR.'/coverage.json' : ECOSYSTEM_DIR.'/coverage.json';
    $coverage = readJson($coverageFile);
    $entries = $coverage['countries'] ?? [];
    $limit ??= $cause === 'country' ? 12 : 18;
    $limit = max(1, min($limit, 30));

    if ($cause === 'country') {
        $firstPassRemaining = array_filter(
            $entries,
            fn (array $entry): bool => in_array($entry['status'] ?? null, ['seeded', 'unstarted'], true),
        );
        $phase = $firstPassRemaining !== [] ? 'worldwide_file_build' : 'depth_completion';
        $priority = $phase === 'worldwide_file_build'
            ? ['seeded' => 0, 'unstarted' => 1]
            : ['complete' => 0, 'partial' => 1];
    } else {
        $phase = 'worldwide_breadth';
        $priority = ['partial' => 0, 'unstarted' => 1];
    }

    $pending = array_filter($entries, function (array $entry) use ($priority, $cause, $phase): bool {
        if ($cause === 'country' && $phase === 'depth_completion') {
            return ($entry['status'] ?? null) === 'partial'
                || ($entry['extension_status'] ?? null) === 'review_required';
        }

        return isset($priority[$entry['status'] ?? '']);
    });
    uasort($pending, function (array $left, array $right) use ($priority, $registry): int {
        $byStatus = $priority[$left['status']] <=> $priority[$right['status']];
        if ($byStatus !== 0) {
            return $byStatus;
        }

        $leftCountry = $registry[$left['iso3']];
        $rightCountry = $registry[$right['iso3']];

        return [$leftCountry['desk'] ?? '', $left['iso3']] <=> [$rightCountry['desk'] ?? '', $right['iso3']];
    });

    $selected = array_slice($pending, 0, $limit, true);
    $packets = [];
    foreach ($selected as $iso3 => $entry) {
        $packets[] = [
            'iso3' => $iso3,
            'name' => $registry[$iso3]['name'],
            'region' => $registry[$iso3]['region'] ?? null,
            'desk' => $registry[$iso3]['desk'] ?? null,
            'sovereign' => $registry[$iso3]['sovereign'] ?? null,
            'status' => $entry['status'],
            'core_status' => $entry['core_status'] ?? null,
            'extension_status' => $entry['extension_status'] ?? null,
            'work_type' => ($entry['extension_status'] ?? null) === 'review_required'
                ? 'protected_media_review'
                : $phase,
            'remaining' => $entry['missing'] ?? $entry['remaining'] ?? [],
        ];
    }

    echo json_encode([
        'cause' => $cause,
        'phase' => $phase,
        'generated_at' => gmdate(DATE_ATOM),
        'limit' => $limit,
        'pending_total' => count($pending),
        'campaign_incomplete_total' => count(array_filter(
            $entries,
            fn (array $entry): bool => $cause === 'country'
                ? in_array($entry['status'] ?? null, ['seeded', 'unstarted', 'partial'], true)
                    || ($entry['extension_status'] ?? null) === 'review_required'
                : in_array($entry['status'] ?? null, ['unstarted', 'partial'], true),
        )),
        'batch' => $packets,
        'instruction' => $phase === 'worldwide_file_build'
            ? 'Give every map entry a first researched file before returning to partial files for depth completion.'
            : 'Finish and checkpoint this batch before requesting another queue.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
}

function campaign(string $cause, int $target, int $waveSize): void
{
    if ($cause !== 'country') {
        throw new InvalidArgumentException('The optimized campaign currently supports country only.');
    }

    $target = max(1, min($target, 235));
    $waveSize = max(4, min($waveSize, 24));
    $registry = registry();
    $coverage = readJson(COUNTRY_DIR.'/coverage.json');
    $entries = $coverage['countries'] ?? [];
    $firstPass = array_filter(
        $entries,
        fn (array $entry): bool => in_array($entry['status'] ?? null, ['seeded', 'unstarted'], true),
    );

    $phase = $firstPass !== [] ? 'worldwide_file_build' : 'depth_completion';
    $candidates = $firstPass !== [] ? $firstPass : array_filter(
        $entries,
        fn (array $entry): bool => ($entry['status'] ?? null) === 'partial'
            || ($entry['extension_status'] ?? null) === 'review_required',
    );
    uasort($candidates, function (array $left, array $right) use ($registry, $phase): int {
        $priority = $phase === 'worldwide_file_build'
            ? ['seeded' => 0, 'unstarted' => 1]
            : ['complete' => 0, 'partial' => 1];
        $byStatus = $priority[$left['status']] <=> $priority[$right['status']];
        if ($byStatus !== 0) {
            return $byStatus;
        }

        $leftCountry = $registry[$left['iso3']];
        $rightCountry = $registry[$right['iso3']];

        return [$leftCountry['desk'] ?? '', $left['iso3']] <=> [$rightCountry['desk'] ?? '', $right['iso3']];
    });

    $selected = array_slice($candidates, 0, $target, true);
    $packets = [];
    foreach ($selected as $iso3 => $entry) {
        $packets[] = [
            'iso3' => $iso3,
            'name' => $registry[$iso3]['name'],
            'desk' => $registry[$iso3]['desk'] ?? null,
            'sovereign' => $registry[$iso3]['sovereign'] ?? null,
            'status' => $entry['status'],
            'core_status' => $entry['core_status'] ?? null,
            'extension_status' => $entry['extension_status'] ?? null,
            'work_type' => ($entry['extension_status'] ?? null) === 'review_required'
                ? 'protected_media_review'
                : $phase,
        ];
    }

    $waves = [];
    foreach (array_chunk($packets, $waveSize) as $index => $wave) {
        $waves[] = [
            'wave' => $index + 1,
            'count' => count($wave),
            'iso3' => array_column($wave, 'iso3'),
            'countries' => $wave,
            'checkpoint' => 'Merge accepted packets, run validate and audit, then commit before starting the next wave.',
        ];
    }

    echo json_encode([
        'cause' => 'country',
        'mode' => $phase === 'worldwide_file_build' ? 'low_cost_core_first' : 'low_cost_depth',
        'phase' => $phase,
        'generated_at' => gmdate(DATE_ATOM),
        'requested_target' => $target,
        'selected' => count($packets),
        'wave_size' => $waveSize,
        'wave_count' => count($waves),
        'remaining_after_campaign' => max(0, count($candidates) - count($packets)),
        'rules' => [
            'The main Sonnet session is the VP and sole writer.',
            'Launch Haiku country-manager agents directly; do not launch directors.',
            'Use cached helper fetches before model browsing.',
            'Core-first verifies leadership, lead body, strategy/law/budget, core stakeholders and media.',
            'Unresolved depth remains explicit and partial.',
            'Stop before the account campaign budget is exceeded.',
        ],
        'waves' => $waves,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
}

function protectCountry(string $productionPath, ?string $sourceRef): void
{
    if ($productionPath === '' || ! file_exists($productionPath)) {
        throw new InvalidArgumentException('protect-country requires a readable production atlas.json path.');
    }

    $production = readJson($productionPath);
    $productionFiles = $production['cfiles'] ?? null;
    if (! is_array($productionFiles)) {
        throw new InvalidArgumentException('Production atlas JSON does not contain a cfiles object.');
    }

    $authoritativeCountries = array_filter(
        $productionFiles,
        fn (mixed $file): bool => is_array($file) && ($file['verify'] ?? null) === 'verified',
    );
    ksort($authoritativeCountries);
    $snapshotHash = countrySnapshotHash($authoritativeCountries);
    if (count($authoritativeCountries) !== AUTHORITATIVE_COUNTRY_COUNT
        || $snapshotHash !== AUTHORITATIVE_COUNTRY_SHA256) {
        throw new RuntimeException(
            'Authoritative import rejected: expected the explicitly approved '
            .AUTHORITATIVE_COUNTRY_COUNT.'-file snapshot '.AUTHORITATIVE_COUNTRY_SHA256
            .", received ".count($authoritativeCountries)."-file snapshot {$snapshotHash}. "
            .'A site engineer must review production changes and update the pinned count/hash explicitly.',
        );
    }

    $database = readJson(COUNTRY_DIR.'/database.json');
    $campaignFiles = $database['research_files'] ?? [];
    $extensions = $database['extensions'] ?? [];
    $proposedChanges = $database['proposed_changes'] ?? [];

    foreach ($authoritativeCountries as $iso3 => $authoritative) {
        $campaign = $campaignFiles[$iso3] ?? ($database['files'][$iso3] ?? null);
        if (is_array($campaign) && $campaign !== $authoritative) {
            $campaignFiles[$iso3] = $campaign;
            $extensions[$iso3] = buildCountryExtension(
                $iso3,
                $authoritative,
                $campaign,
            );
            $proposedChanges[$iso3] = countryCoreDiff($iso3, $authoritative, $campaign);
        }
        $database['files'][$iso3] = $authoritative;
    }

    ksort($campaignFiles);
    ksort($extensions);
    ksort($proposedChanges);
    ksort($database['files']);

    $sourceRef ??= 'gabbus380/space-atlas resources/atlas/data/atlas.json';
    writeJson(AUTHORITATIVE_COUNTRY_FILE, [
        'schema' => 1,
        'captured_on' => gmdate('Y-m-d'),
        'source_ref' => $sourceRef,
        'countries_sha256' => $snapshotHash,
        'rule' => 'Every object in countries is an immutable production-verified core. Extend it additively; never replace it with campaign research.',
        'countries' => $authoritativeCountries,
    ]);

    $baseline = readJson(COUNTRY_DIR.'/baseline.json');
    $baseline['countries'] = array_diff_key($baseline['countries'] ?? [], $authoritativeCountries);
    $baseline['not_proof'] = 'These unverified legacy files are starting text, not current proof. Production-verified files live only in authoritative.json.';
    ksort($baseline['countries']);
    writeJson(COUNTRY_DIR.'/baseline.json', $baseline);

    $database = [
        'schema' => 2,
        'asOf' => $database['asOf'] ?? gmdate('Y-m-d'),
        'source' => $database['source'] ?? 'ATLAS Vice President of Data Analytics',
        'registry' => $database['registry'] ?? 'claude/atlas/registry/countries.json',
        'baseline' => $database['baseline'] ?? 'claude/atlas/country-data/baseline.json',
        'authoritative' => 'claude/atlas/country-data/authoritative.json',
        'mode' => 'worldwide_build',
        'run' => $database['run'] ?? ['searches_used' => 0, 'fetches_used' => 0, 'stopped_because' => null],
        'files' => $database['files'],
        'research_files' => $campaignFiles,
        'extensions' => $extensions,
        'proposed_changes' => $proposedChanges,
        'evidence' => $database['evidence'] ?? new stdClass(),
        'flags' => $database['flags'] ?? new stdClass(),
        'rejected' => $database['rejected'] ?? new stdClass(),
    ];
    writeJson(COUNTRY_DIR.'/database.json', $database);
    updateProtectedCoverage(array_keys($authoritativeCountries), $database);

    echo 'Protected '.count($authoritativeCountries).' production-verified country cores; campaign research remains review-only.'.PHP_EOL;
}

function buildCountryExtension(
    string $iso3,
    array $authoritative,
    array $campaign,
): array {
    $knownOutletKeys = [];
    foreach ($authoritative['media']['outlets'] ?? [] as $outlet) {
        $key = strtolower((string) ($outlet['url'] ?? $outlet['id'] ?? ''));
        if ($key !== '') {
            $knownOutletKeys[$key] = true;
        }
    }

    $media = $campaign['media'] ?? ['outlets' => [], 'none' => []];
    $media['outlets'] = array_values(array_filter(
        $media['outlets'] ?? [],
        function (array $outlet) use ($knownOutletKeys): bool {
            $key = strtolower((string) ($outlet['url'] ?? $outlet['id'] ?? ''));

            return $key !== '' && ! isset($knownOutletKeys[$key]);
        },
    ));

    return [
        'iso3' => $iso3,
        'status' => 'review_required',
        'asOf' => $campaign['asOf'] ?? null,
        'media' => $media,
        'evidence_ref' => 'database.json#/evidence/'.$iso3.'/media',
        'flags_ref' => 'database.json#/flags/'.$iso3,
        'rule' => 'Candidate additive media extension. It is not active until its evidence and flags are reviewed.',
    ];
}

function countryCoreDiff(string $iso3, array $authoritative, array $campaign): array
{
    unset($authoritative['media'], $campaign['media'], $authoritative['verify'], $campaign['verify']);
    $changes = [];
    foreach (array_unique(array_merge(array_keys($authoritative), array_keys($campaign))) as $section) {
        if (($authoritative[$section] ?? null) === ($campaign[$section] ?? null)) {
            continue;
        }
        $escaped = str_replace(['~', '/'], ['~0', '~1'], (string) $section);
        $changes[] = [
            'path' => '/'.$escaped,
            'authoritative_ref' => 'authoritative.json#/countries/'.$iso3.'/'.$escaped,
            'proposed_ref' => 'database.json#/research_files/'.$iso3.'/'.$escaped,
            'reason' => 'Campaign proposal; requires human or VP evidence review.',
        ];
    }

    return [
        'status' => $changes === [] ? 'no_core_change' : 'review_required',
        'changes' => $changes,
        'rule' => 'Compare the authoritative and proposed values at each reference and review dated evidence. Nothing in this list is active.',
    ];
}

function updateProtectedCoverage(array $protectedIso3, array $database): void
{
    $coverage = readJson(COUNTRY_DIR.'/coverage.json');
    $protected = array_fill_keys($protectedIso3, true);
    $coverage['countries'] ??= [];
    foreach ($coverage['countries'] as $iso3 => &$entry) {
        if (isset($protected[$iso3])) {
            $entry['status'] = 'complete';
            $entry['source'] = 'authoritative';
            $entry['last_checked'] = $database['files'][$iso3]['asOf'] ?? null;
            $entry['core_status'] = 'production_verified';
            $entry['extension_last_checked'] = $database['extensions'][$iso3]['asOf'] ?? null;
            $entry['extension_status'] = ($database['extensions'][$iso3]['status'] ?? null) === 'accepted'
                ? 'complete'
                : 'review_required';
            $entry['campaign_status'] = $database['research_files'][$iso3]['verify'] ?? null;
            $entry['missing'] = ['review additive media extension and any proposed core changes'];
        } else {
            $entry['core_status'] = ($database['files'][$iso3]['verify'] ?? null) === 'verified'
                ? 'verified'
                : 'partial';
            $entry['extension_last_checked'] = null;
            $entry['extension_status'] = 'not_applicable';
            $entry['campaign_status'] = $database['files'][$iso3]['verify'] ?? null;
        }
    }
    unset($entry);
    $coverage['counts'] = countsByStatus($coverage['countries'] ?? [], COUNTRY_STATUSES);
    $coverage['protected_production_files'] = count($protectedIso3);
    $coverage['monthly_ready'] = countryMonthlyReady($coverage['countries']);
    writeJson(COUNTRY_DIR.'/coverage.json', $coverage);
}

function countrySnapshotHash(array $countries): string
{
    ksort($countries);

    return hash('sha256', safeJson($countries));
}

function audit(string $scope): void
{
    if ($scope !== 'country') {
        throw new InvalidArgumentException('Audit scope must be country.');
    }

    $database = readJson(COUNTRY_DIR.'/database.json');
    $authoritativePacket = file_exists(AUTHORITATIVE_COUNTRY_FILE) ? readJson(AUTHORITATIVE_COUNTRY_FILE) : [];
    $authoritative = $authoritativePacket['countries'] ?? [];
    $results = [];
    $unsafeComplete = [];
    $protectedRegressions = [];

    foreach ($database['files'] ?? [] as $iso3 => $file) {
        $issues = [];
        $evidence = $database['evidence'][$iso3] ?? [];
        $flags = $database['flags'][$iso3] ?? [];
        $isProtected = isset($authoritative[$iso3]);

        if ($isProtected && $file !== $authoritative[$iso3]) {
            $protectedRegressions[$iso3] = 'Active file differs from the production-verified authoritative core.';
        }

        if (! $isProtected) {
            foreach (['government.head_of_state', 'government.head_of_government', 'government.lead', 'strategy', 'stakeholders', 'media'] as $key) {
                if (! array_key_exists($key, $evidence)) {
                    $issues[] = "missing evidence section {$key}";
                }
            }
            if (count($file['media']['outlets'] ?? []) < 3) {
                $issues[] = 'fewer than three proved media/official outlets';
            }
        }
        if (($file['government']['nodes'] ?? []) !== []
            && count(array_filter($file['government']['nodes'], fn (array $node): bool => ($node['lead'] ?? false) === true)) !== 1) {
            $issues[] = 'government graph must contain exactly one lead node';
        }
        if (($file['stakeholders']['links'] ?? []) !== []) {
            $nodeIds = [];
            foreach (array_merge($file['government']['nodes'] ?? [], $file['stakeholders']['nodes'] ?? []) as $node) {
                if (isset($node['id'])) {
                    $nodeIds[$node['id']] = true;
                }
            }
            foreach ($file['stakeholders']['links'] as $link) {
                if (! isset($nodeIds[$link['source'] ?? ''], $nodeIds[$link['target'] ?? ''])) {
                    $issues[] = 'stakeholder graph contains an unresolved endpoint';
                    break;
                }
            }
        }

        $results[$iso3] = [
            'verify' => $file['verify'] ?? null,
            'protected_core' => $isProtected,
            'issue_count' => count($issues),
            'declared_flag_count' => count($flags),
            'issues' => $issues,
        ];

        if (! $isProtected && ($file['verify'] ?? null) === 'verified' && ($issues !== [] || $flags !== [])) {
            $unsafeComplete[$iso3] = [
                'machine_issues' => $issues,
                'declared_flags' => $flags,
            ];
        }
    }

    echo json_encode([
        'audited_at' => gmdate(DATE_ATOM),
        'files_audited' => count($results),
        'protected_core_count' => count($authoritative),
        'protected_snapshot_hash_valid' => ($authoritativePacket['countries_sha256'] ?? null) === countrySnapshotHash($authoritative),
        'approved_snapshot_valid' => count($authoritative) === AUTHORITATIVE_COUNTRY_COUNT
            && countrySnapshotHash($authoritative) === AUTHORITATIVE_COUNTRY_SHA256,
        'protected_regression_count' => count($protectedRegressions),
        'protected_regressions' => $protectedRegressions,
        'extension_review_count' => count(array_filter(
            $database['extensions'] ?? [],
            fn (array $extension): bool => ($extension['status'] ?? null) === 'review_required',
        )),
        'partial_active_count' => count(array_filter(
            $database['files'] ?? [],
            fn (array $file): bool => ($file['verify'] ?? null) !== 'verified',
        )),
        'declared_flag_total' => array_sum(array_map('count', $database['flags'] ?? [])),
        'unsafe_complete_count' => count($unsafeComplete),
        'unsafe_complete' => $unsafeComplete,
        'results' => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;

    if ($unsafeComplete !== []
        || $protectedRegressions !== []
        || ($authoritativePacket['countries_sha256'] ?? null) !== countrySnapshotHash($authoritative)
        || count($authoritative) !== AUTHORITATIVE_COUNTRY_COUNT
        || countrySnapshotHash($authoritative) !== AUTHORITATIVE_COUNTRY_SHA256) {
        exit(1);
    }
}

function validate(string $scope): void
{
    if (! in_array($scope, ['all', 'country', 'ecosystem'], true)) {
        throw new InvalidArgumentException('Validation scope must be all, country, or ecosystem.');
    }

    $registry = registry();
    $errors = [];
    $warnings = [];

    if (count($registry) !== 235) {
        $errors[] = 'Registry must contain exactly 235 entries; found '.count($registry).'.';
    }

    if ($scope !== 'ecosystem') {
        validateCountry($registry, $errors, $warnings);
    }
    if ($scope !== 'country') {
        validateEcosystem($registry, $errors, $warnings);
    }

    foreach ($warnings as $warning) {
        fwrite(STDERR, 'WARNING: '.$warning.PHP_EOL);
    }
    if ($errors !== []) {
        foreach ($errors as $error) {
            fwrite(STDERR, 'ERROR: '.$error.PHP_EOL);
        }
        exit(1);
    }

    echo 'Validated '.$scope.' builders against '.count($registry).' map entries';
    echo $warnings === [] ? ".\n" : ' with '.count($warnings)." warning(s).\n";
}

function validateCountry(array $registry, array &$errors, array &$warnings): void
{
    $coverage = readJson(COUNTRY_DIR.'/coverage.json');
    $database = readJson(COUNTRY_DIR.'/database.json');
    $baseline = readJson(COUNTRY_DIR.'/baseline.json');
    $authoritativePacket = file_exists(AUTHORITATIVE_COUNTRY_FILE) ? readJson(AUTHORITATIVE_COUNTRY_FILE) : [];
    $authoritative = $authoritativePacket['countries'] ?? [];
    validateCoverageKeys('country', $registry, $coverage['countries'] ?? [], $errors);

    if (($database['schema'] ?? null) !== 2) {
        $errors[] = 'Country database must use layered schema 2.';
    }
    if (($database['authoritative'] ?? null) !== 'claude/atlas/country-data/authoritative.json') {
        $errors[] = 'Country database must reference the authoritative production snapshot.';
    }
    if (count($database['files'] ?? []) !== count($registry)) {
        $errors[] = 'Country database must contain one active file for every map entry.';
    }
    if ($authoritative !== []
        && ($authoritativePacket['countries_sha256'] ?? null) !== countrySnapshotHash($authoritative)) {
        $errors[] = 'Authoritative country snapshot hash does not match its contents.';
    }
    if (count($authoritative) !== AUTHORITATIVE_COUNTRY_COUNT
        || countrySnapshotHash($authoritative) !== AUTHORITATIVE_COUNTRY_SHA256) {
        $errors[] = 'Authoritative countries do not match the independently pinned production snapshot.';
    }
    if (($coverage['monthly_ready'] ?? null) !== countryMonthlyReady($coverage['countries'] ?? [])) {
        $errors[] = 'Country coverage monthly_ready does not match core and extension completion.';
    }
    $baselineOverlap = array_intersect_key($baseline['countries'] ?? [], $authoritative);
    if ($baselineOverlap !== []) {
        $errors[] = 'Production-authoritative countries must not also remain in the unverified baseline: '
            .implode(', ', array_keys($baselineOverlap)).'.';
    }

    foreach ($coverage['countries'] ?? [] as $iso3 => $entry) {
        if (($entry['iso3'] ?? null) !== $iso3) {
            $errors[] = "Country coverage key {$iso3} does not match its iso3.";
        }
        if (! in_array($entry['status'] ?? null, COUNTRY_STATUSES, true)) {
            $errors[] = "Country {$iso3} has an invalid status.";
        }
    }

    foreach ($database['files'] ?? [] as $iso3 => $file) {
        if (! isset($registry[$iso3])) {
            $errors[] = "Country database has unknown ISO3 {$iso3}.";
            continue;
        }
        $isProtected = isset($authoritative[$iso3]);
        if ($isProtected && $file !== $authoritative[$iso3]) {
            $errors[] = "Country {$iso3} differs from its production-verified authoritative core.";
        }
        if (($file['iso3'] ?? null) !== $iso3) {
            $errors[] = "Country file {$iso3} has a missing or different iso3.";
        }
        $requiredFields = ['asOf', 'verify', 'government', 'stakeholders', 'strategy'];
        if (! $isProtected) {
            $requiredFields[] = 'media';
        }
        foreach ($requiredFields as $required) {
            if (! array_key_exists($required, $file)) {
                $errors[] = "Country file {$iso3} lacks {$required}.";
            }
        }
        if (! $isProtected) {
            validateCountryMedia($iso3, $file['media']['outlets'] ?? [], $database['evidence'][$iso3]['media'] ?? [], $errors);
        }
    }

    foreach ($authoritative as $iso3 => $file) {
        if (($file['verify'] ?? null) !== 'verified') {
            $errors[] = "Authoritative country {$iso3} is not production verified.";
        }
        if (! isset($database['research_files'][$iso3], $database['extensions'][$iso3], $database['proposed_changes'][$iso3])) {
            $errors[] = "Protected country {$iso3} is missing preserved research, extension, or proposed-change data.";
            continue;
        }
        $extension = $database['extensions'][$iso3];
        validateCountryMedia(
            $iso3.' extension',
            $extension['media']['outlets'] ?? [],
            $database['evidence'][$iso3]['media'] ?? [],
            $errors,
        );
        if (($coverage['countries'][$iso3]['core_status'] ?? null) !== 'production_verified') {
            $errors[] = "Protected country {$iso3} is not marked production_verified in coverage.";
        }
    }
}

function validateCountryMedia(string $iso3, array $outlets, array $evidence, array &$errors): void
{
    $ids = [];
    foreach ($outlets as $outlet) {
        $id = $outlet['id'] ?? '';
        if ($id === '' || isset($ids[$id])) {
            $errors[] = "Country {$iso3} has a missing or duplicate media outlet id.";
            continue;
        }
        $ids[$id] = true;
        if (! in_array($outlet['kind'] ?? null, MEDIA_KINDS, true)) {
            $errors[] = "Country {$iso3} outlet {$id} has an invalid kind.";
        }
        if (! validPublicUrl($outlet['url'] ?? '') || forbiddenDiscoveryUrl($outlet['url'])) {
            $errors[] = "Country {$iso3} outlet {$id} has an invalid or discovery-only URL.";
        }
        if (! isset($evidence[$id])) {
            $errors[] = "Country {$iso3} outlet {$id} has no separate evidence.";
        }
    }
}

function validateEcosystem(array $registry, array &$errors, array &$warnings): void
{
    $baseline = readJson(ECOSYSTEM_DIR.'/baseline.json');
    $coverage = readJson(ECOSYSTEM_DIR.'/coverage.json');
    $database = readJson(ECOSYSTEM_DIR.'/database.json');
    validateCoverageKeys('ecosystem', $registry, $coverage['countries'] ?? [], $errors);
    validateCoverageKeys('ecosystem database', $registry, $database['coverage'] ?? [], $errors);

    $categories = $baseline['categories'] ?? [];
    $domains = $baseline['domains'] ?? [];
    $knownIds = [];
    foreach ($baseline['organizations'] ?? [] as $organization) {
        $knownIds[$organization['id']] = true;
    }

    foreach ($database['upserts'] ?? [] as $organization) {
        $id = $organization['id'] ?? '';
        if ($id === '' || isset($knownIds[$id]) && isset($seenUpserts[$id])) {
            $errors[] = 'Ecosystem has a missing or duplicate upsert id.';
            continue;
        }
        $seenUpserts[$id] = true;
        $knownIds[$id] = true;
        $iso3 = $organization['iso3'] ?? '';
        if (! isset($registry[$iso3])) {
            $errors[] = "Organization {$id} has unknown ISO3 {$iso3}.";
        }
        if (! in_array($organization['category'] ?? null, $categories, true)) {
            $errors[] = "Organization {$id} has an invalid category.";
        }
        if (! in_array($organization['type'] ?? null, ORG_TYPES, true)) {
            $errors[] = "Organization {$id} has an invalid type.";
        }
        foreach ($organization['domains'] ?? [] as $domain) {
            if (! in_array($domain, $domains, true)) {
                $errors[] = "Organization {$id} has invalid domain {$domain}.";
            }
        }
        if (! validPublicUrl($organization['source'] ?? '')) {
            $errors[] = "Organization {$id} has no valid source URL.";
        }
        if (array_key_exists('url', $organization) || array_key_exists('proof', $organization)) {
            $errors[] = "Organization {$id} contains workflow fields inside the website object.";
        }
        if (! isset($database['evidence'][$id])) {
            $errors[] = "Organization {$id} has no separate evidence.";
        }
    }

    foreach ($database['coverage'] ?? [] as $iso3 => $entry) {
        if (! in_array($entry['status'] ?? null, ECOSYSTEM_STATUSES, true)) {
            $errors[] = "Ecosystem {$iso3} has an invalid status.";
        }
        if (! in_array($entry['depth_status'] ?? null, ECOSYSTEM_DEPTH_STATUSES, true)) {
            $errors[] = "Ecosystem {$iso3} has an invalid depth status.";
        }
        foreach ($entry['organization_ids'] ?? [] as $id) {
            if (! isset($knownIds[$id])) {
                $errors[] = "Ecosystem {$iso3} references unknown organization {$id}.";
            }
        }
        if (in_array($entry['status'] ?? null, ['mapped', 'inherited', 'none_found'], true)
            && empty((array) ($entry['proof'] ?? []))) {
            $errors[] = "Ecosystem {$iso3} is complete without coverage proof.";
        }
    }
}

function validateCoverageKeys(string $label, array $registry, array $entries, array &$errors): void
{
    $missing = array_diff_key($registry, $entries);
    $extra = array_diff_key($entries, $registry);

    if ($missing !== []) {
        $errors[] = ucfirst($label).' coverage misses: '.implode(', ', array_keys($missing)).'.';
    }
    if ($extra !== []) {
        $errors[] = ucfirst($label).' coverage has unknown entries: '.implode(', ', array_keys($extra)).'.';
    }
}

function fetchSource(string $url): void
{
    if (! validPublicUrl($url)) {
        throw new InvalidArgumentException('Fetch requires a public http(s) URL without credentials.');
    }
    if (! extension_loaded('curl')) {
        throw new RuntimeException('The PHP cURL extension is required for fetch.');
    }

    $cacheDirectory = ROOT.'/../../.atlas-cache/sources';
    $cachePath = $cacheDirectory.'/'.hash('sha256', $url).'.json';
    if (file_exists($cachePath) && filemtime($cachePath) >= time() - 86400) {
        try {
            $cached = readJson($cachePath);
            if (($cached['schema'] ?? null) === FETCH_SCHEMA && ($cached['usable'] ?? false) === true) {
                $cached['cache_hit'] = true;
                echo safeJson($cached).PHP_EOL;

                return;
            }
        } catch (JsonException) {
            // Ignore malformed legacy cache entries and replace them after a usable fetch.
        }
    }

    $direct = request($url);
    $selected = $direct;
    $via = 'direct';
    $fallbackReason = null;
    $directText = responseText($direct);
    $needsFallback = ! responseUsable($direct, $directText)
        || isPdfResponse($direct)
        || looksScriptOnly($direct['body'], $directText);

    if ($needsFallback) {
        $fallbackReason = isPdfResponse($direct)
            ? 'Origin returned a PDF; requested a text rendering so raw PDF bytes are never cached as text.'
            : 'Origin returned HTTP '.$direct['status'].', blocked/script-only content, or too little readable text.';
        $readerUrl = jinaReaderUrl($url);
        $fallback = request($readerUrl);
        $fallbackText = responseText($fallback);
        if (responseUsable($fallback, $fallbackText)) {
            $selected = $fallback;
            $via = 'jina_text_reader';
        }
    }

    $text = responseText($selected);
    $title = extractTitle(normalizeUtf8($selected['body']), $selected['content_type']);
    $usable = responseUsable($selected, $text);
    $limit = max(20000, min((int) (getenv('ATLAS_FETCH_TEXT_LIMIT') ?: 100000), 500000));
    $textLength = mb_strlen($text);
    $packet = [
        'schema' => FETCH_SCHEMA,
        'requested_url' => $url,
        'retrieved_on' => gmdate('Y-m-d'),
        'retrieved_at' => gmdate(DATE_ATOM),
        'via' => $via,
        'independent_source' => false,
        'origin_http_status' => $direct['status'],
        'origin_transport_error' => $direct['error'] !== '' ? normalizeUtf8($direct['error']) : null,
        'http_status' => $selected['status'],
        'content_type' => $selected['content_type'],
        'final_url' => $selected['final_url'],
        'page_title' => $title,
        'usable' => $usable,
        'cache_hit' => false,
        'content_sha256' => hash('sha256', $selected['body']),
        'content_bytes' => strlen($selected['body']),
        'text_characters' => $textLength,
        'text_truncated' => $textLength > $limit,
        'text' => mb_substr($text, 0, $limit),
        'fallback_reason' => $fallbackReason,
        'verification_note' => ! $usable
            ? 'The result is still blocked or unreadable. Find an official mirror, document repository or corroborating source; do not cite this packet as proof.'
            : ($via === 'direct'
                ? 'Direct origin fetch. Confirm that the page supports each claim.'
                : 'Third-party text rendering of the named origin. Preserve the origin URL and corroborate time-sensitive claims.'),
    ];

    if (! is_dir($cacheDirectory) && ! mkdir($cacheDirectory, 0775, true) && ! is_dir($cacheDirectory)) {
        throw new RuntimeException("Could not create source cache: {$cacheDirectory}");
    }
    if ($usable) {
        writeJson($cachePath, $packet);
    }
    echo safeJson($packet).PHP_EOL;
}

function request(string $url): array
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SovereignSpaceATLASResearch/1.0; +https://atlas.emmaconsulting.space)',
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/pdf,application/json,text/plain;q=0.9,*/*;q=0.5'],
    ]);
    $body = curl_exec($handle);
    $error = curl_error($handle);
    $info = curl_getinfo($handle);

    return [
        'status' => (int) ($info['http_code'] ?? 0),
        'content_type' => (string) ($info['content_type'] ?? ''),
        'final_url' => (string) ($info['url'] ?? $url),
        'body' => is_string($body) ? $body : '',
        'error' => $error,
    ];
}

function extractTitle(string $body, string $contentType): ?string
{
    if (str_contains(strtolower($contentType), 'html') && preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $match)) {
        return trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if (preg_match('/^Title:\s*(.+)$/mi', $body, $match)) {
        return trim($match[1]);
    }

    return null;
}

function extractText(string $body, string $contentType): string
{
    $body = normalizeUtf8($body, $contentType);
    if (str_contains(strtolower($contentType), 'html')) {
        if (class_exists(DOMDocument::class)) {
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML(
                '<?xml encoding="UTF-8">'.$body,
                LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | LIBXML_COMPACT,
            );
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new DOMXPath($document);
            foreach ($xpath->query('//script|//style|//noscript|//svg|//template') ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }
            $body = $document->getElementsByTagName('body')->item(0)?->textContent ?? $document->textContent;
        } else {
            $body = preg_replace('/<(script|style|noscript|svg|template)\b[^>]*>.*?<\/\1>/is', ' ', $body) ?? $body;
            $body = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    $body = preg_replace('/[ \t]+/u', ' ', $body) ?? $body;

    return trim(preg_replace('/(?:\r?\n\s*){3,}/u', "\n\n", $body) ?? $body);
}

function responseText(array $response): string
{
    if (isPdfResponse($response)) {
        return extractPdfText($response['body']);
    }

    return extractText($response['body'], $response['content_type']);
}

function responseUsable(array $response, string $text): bool
{
    if (($response['error'] ?? '') !== ''
        || ($response['status'] ?? 0) < 200
        || ($response['status'] ?? 0) >= 300
        || mb_strlen(trim($text)) < 200) {
        return false;
    }

    return ! looksBlocked(extractTitle(normalizeUtf8($response['body']), $response['content_type']), $text);
}

function isPdfResponse(array $response): bool
{
    return str_contains(strtolower((string) ($response['content_type'] ?? '')), 'application/pdf')
        || str_starts_with((string) ($response['body'] ?? ''), '%PDF-');
}

function extractPdfText(string $body): string
{
    $binary = tempnam(sys_get_temp_dir(), 'atlas-pdf-');
    $output = tempnam(sys_get_temp_dir(), 'atlas-text-');
    if ($binary === false || $output === false) {
        return '';
    }

    try {
        file_put_contents($binary, $body);
        $pdftotext = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
        if ($pdftotext === '') {
            return '';
        }
        $command = escapeshellarg($pdftotext).' -enc UTF-8 -layout '
            .escapeshellarg($binary).' '.escapeshellarg($output).' 2>/dev/null';
        exec($command, $ignored, $status);
        if ($status !== 0) {
            return '';
        }

        return normalizeUtf8((string) file_get_contents($output));
    } finally {
        @unlink($binary);
        @unlink($output);
    }
}

function jinaReaderUrl(string $url): string
{
    return 'https://r.jina.ai/http'.(parse_url($url, PHP_URL_SCHEME) === 'https' ? 's' : '').'://'
        .parse_url($url, PHP_URL_HOST)
        .(parse_url($url, PHP_URL_PORT) ? ':'.parse_url($url, PHP_URL_PORT) : '')
        .(parse_url($url, PHP_URL_PATH) ?: '/')
        .(parse_url($url, PHP_URL_QUERY) ? '?'.parse_url($url, PHP_URL_QUERY) : '');
}

function looksScriptOnly(string $html, string $text): bool
{
    if (! str_contains(strtolower($html), '<script')) {
        return false;
    }

    return mb_strlen(trim($text)) < 500;
}

function looksBlocked(?string $title, string $text): bool
{
    $sample = strtolower(($title ?? '').' '.mb_substr($text, 0, 1000));

    foreach ([
        'just a moment',
        'attention required',
        'enable javascript and cookies',
        'cf-chl-',
        'captcha',
        'access denied',
        'request blocked',
        'bad gateway',
        'dns resolution failure',
        'upstream connect error',
        'server error',
    ] as $marker) {
        if (str_contains($sample, $marker)) {
            return true;
        }
    }

    return false;
}

function normalizeUtf8(string $value, string $contentType = ''): string
{
    if ($value === '') {
        return '';
    }

    $encoding = null;
    if (preg_match('/charset\s*=\s*["\']?([a-z0-9._-]+)/i', $contentType, $match)) {
        $encoding = $match[1];
    } elseif (preg_match('/<meta[^>]+charset\s*=\s*["\']?([a-z0-9._-]+)/i', substr($value, 0, 4096), $match)) {
        $encoding = $match[1];
    }

    if ($encoding !== null && strcasecmp($encoding, 'utf-8') !== 0) {
        try {
            $converted = mb_convert_encoding($value, 'UTF-8', $encoding);
        } catch (ValueError) {
            $converted = false;
        }
        if (is_string($converted)) {
            $value = $converted;
        }
    }
    if (! mb_check_encoding($value, 'UTF-8')) {
        $detected = mb_detect_encoding($value, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
        $value = $detected === false
            ? (string) iconv('UTF-8', 'UTF-8//IGNORE', $value)
            : mb_convert_encoding($value, 'UTF-8', $detected);
    }

    return $value;
}

function validPublicUrl(mixed $url): bool
{
    if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $parts = parse_url($url);
    if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }
    $host = strtolower($parts['host'] ?? '');
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    return true;
}

function forbiddenDiscoveryUrl(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    return $host === 'wikipedia.org'
        || str_ends_with($host, '.wikipedia.org')
        || in_array($host, ['facebook.com', 'www.facebook.com', 'x.com', 'twitter.com', 'www.linkedin.com', 'instagram.com'], true);
}

function registry(): array
{
    $registry = readJson(REGISTRY_FILE);
    $countries = $registry['countries'] ?? [];
    ksort($countries);

    return $countries;
}

function countsByStatus(array $entries, array $statuses): array
{
    $counts = array_fill_keys($statuses, 0);
    foreach ($entries as $entry) {
        $status = $entry['status'] ?? null;
        if (isset($counts[$status])) {
            $counts[$status]++;
        }
    }

    return $counts;
}

function countryMonthlyReady(array $entries): bool
{
    if (count($entries) !== 235) {
        return false;
    }
    foreach ($entries as $entry) {
        if (! in_array($entry['status'] ?? null, ['complete', 'inherited'], true)
            || ($entry['extension_status'] ?? null) === 'review_required') {
            return false;
        }
    }

    return true;
}

function readJson(string $path): array
{
    if (! file_exists($path)) {
        throw new RuntimeException("Required file does not exist: {$path}");
    }

    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

function safeJson(mixed $data): string
{
    return json_encode(
        normalizeJsonValue($data),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );
}

function normalizeJsonValue(mixed $value): mixed
{
    if (is_string($value)) {
        return normalizeUtf8($value);
    }
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = normalizeJsonValue($item);
        }

        return $value;
    }
    if (is_object($value)) {
        foreach (get_object_vars($value) as $key => $item) {
            $value->{$key} = normalizeJsonValue($item);
        }
    }

    return $value;
}

function writeJson(string $path, array $data): void
{
    file_put_contents($path, safeJson($data).PHP_EOL);
}

function selfTest(): void
{
    $failures = [];
    $assert = static function (bool $condition, string $message) use (&$failures): void {
        if (! $condition) {
            $failures[] = $message;
        }
    };

    $windowsText = "Magyarorsz\xE1g";
    $assert(normalizeUtf8($windowsText, 'text/plain; charset=windows-1252') === 'Magyarország', 'Windows-1252 conversion failed.');
    $assert(
        mb_check_encoding(normalizeUtf8($windowsText, 'text/plain; charset=not-a-real-encoding'), 'UTF-8'),
        'Unknown charset fallback failed.',
    );
    $assert(str_contains(safeJson(['broken' => "bad\xC3("]), '"broken"'), 'Malformed UTF-8 JSON safety failed.');
    $hungarian = 'Árvíztűrő tükörfúrógép';
    $assert(
        extractText('<html><body><p>'.$hungarian.'</p></body></html>', 'text/html; charset=UTF-8') === $hungarian,
        'DOM UTF-8 extraction failed.',
    );

    $badGateway = [
        'status' => 502,
        'content_type' => 'text/html',
        'body' => str_repeat('Bad Gateway ', 30),
        'error' => '',
    ];
    $assert(! responseUsable($badGateway, responseText($badGateway)), 'HTTP 502 must never be usable.');

    $challenge = [
        'status' => 200,
        'content_type' => 'text/html; charset=UTF-8',
        'body' => '<html><title>Just a moment</title><body>'.str_repeat('Enable JavaScript and cookies. ', 20).'</body></html>',
        'error' => '',
    ];
    $assert(! responseUsable($challenge, responseText($challenge)), 'Challenge page must never be usable.');

    $pdf = ['content_type' => 'application/octet-stream', 'body' => '%PDF-1.7 binary'];
    $assert(isPdfResponse($pdf), 'PDF signature detection failed.');

    $diff = countryCoreDiff('TST', ['name' => 'old'], ['name' => 'new']);
    $assert(($diff['changes'][0]['path'] ?? null) === '/name', 'Reviewable country diff failed.');

    $snapshot = ['TST' => ['verify' => 'verified', 'name' => 'Trusted']];
    $snapshotHash = countrySnapshotHash($snapshot);
    $snapshot['TST']['name'] = 'Mutated';
    $assert($snapshotHash !== countrySnapshotHash($snapshot), 'Protected snapshot mutation was not detected.');

    if ($failures !== []) {
        throw new RuntimeException('Self-test failed: '.implode(' ', $failures));
    }

    echo "Builder self-tests passed.\n";
}
