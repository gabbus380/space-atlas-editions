<?php

declare(strict_types=1);

/**
 * Deterministic preparation and validation for the ATLAS country/ecosystem builders.
 *
 * Commands:
 *   php claude/atlas/tools/builders.php initialize
 *   php claude/atlas/tools/builders.php validate [all|country|ecosystem]
 *   php claude/atlas/tools/builders.php queue <country|ecosystem> [limit]
 *   php claude/atlas/tools/builders.php fetch <https://public.example/page>
 */

const ROOT = __DIR__.'/..';
const COUNTRY_DIR = ROOT.'/country-data';
const ECOSYSTEM_DIR = ROOT.'/ecosystem';
const REGISTRY_FILE = ROOT.'/registry/countries.json';

const COUNTRY_STATUSES = ['unstarted', 'seeded', 'partial', 'complete', 'inherited'];
const ECOSYSTEM_STATUSES = ['unstarted', 'partial', 'mapped', 'inherited', 'none_found'];
const ECOSYSTEM_DEPTH_STATUSES = ['not_started', 'in_progress', 'complete', 'not_applicable'];
const MEDIA_KINDS = ['space_agency', 'space_office', 'government', 'national_press', 'space_press', 'science', 'business', 'broadcast'];
const ORG_TYPES = ['Company', 'University', 'Institute', 'Agency', 'Program', 'Cluster', 'Investor', 'Other', null];

main($argv);

function main(array $argv): void
{
    $command = $argv[1] ?? 'help';

    try {
        match ($command) {
            'initialize' => initialize(),
            'validate' => validate($argv[2] ?? 'all'),
            'queue' => queue($argv[2] ?? '', isset($argv[3]) ? (int) $argv[3] : null),
            'fetch' => fetchSource($argv[2] ?? ''),
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

  fetch <public http(s) URL>
      Fetch a page directly, then retry through the Jina text reader when the
      origin blocks automation. Emits a provenance packet; it does not decide
      whether the source proves a claim.
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

        $entries[$iso3] = [
            'iso3' => $iso3,
            'status' => $status,
            'last_checked' => $status === 'unstarted'
                ? null
                : ($files[$iso3]['asOf'] ?? $previousCoverage[$iso3]['last_checked'] ?? null),
            'missing' => ($previousCoverage[$iso3]['status'] ?? null) === $status
                ? ($previousCoverage[$iso3]['missing'] ?? countryMissing($status))
                : countryMissing($status),
            'source' => $source,
        ];
    }

    writeJson(COUNTRY_DIR.'/coverage.json', [
        'schema' => 1,
        'asOf' => $today,
        'mode' => 'worldwide_build',
        'total_map_entries' => count($registry),
        'counts' => countsByStatus($entries, COUNTRY_STATUSES),
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
    $priority = $cause === 'country'
        ? ['partial' => 0, 'seeded' => 1, 'unstarted' => 2]
        : ['partial' => 0, 'unstarted' => 1];

    $pending = array_filter(
        $entries,
        fn (array $entry): bool => isset($priority[$entry['status'] ?? '']),
    );
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
            'remaining' => $entry['missing'] ?? $entry['remaining'] ?? [],
        ];
    }

    echo json_encode([
        'cause' => $cause,
        'generated_at' => gmdate(DATE_ATOM),
        'limit' => $limit,
        'pending_total' => count($pending),
        'batch' => $packets,
        'instruction' => 'Finish and checkpoint this batch before requesting another queue.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
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
    validateCoverageKeys('country', $registry, $coverage['countries'] ?? [], $errors);

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
        if (($file['iso3'] ?? null) !== $iso3) {
            $errors[] = "Country file {$iso3} has a missing or different iso3.";
        }
        foreach (['asOf', 'verify', 'government', 'stakeholders', 'strategy', 'media'] as $required) {
            if (! array_key_exists($required, $file)) {
                $errors[] = "Country file {$iso3} lacks {$required}.";
            }
        }
        validateCountryMedia($iso3, $file['media']['outlets'] ?? [], $database['evidence'][$iso3]['media'] ?? [], $errors);
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

    $direct = request($url);
    $selected = $direct;
    $via = 'direct';
    $fallbackReason = null;

    if (in_array($direct['status'], [0, 401, 403, 406, 408, 409, 425, 429, 451, 500, 502, 503, 504], true)
        || strlen(trim($direct['body'])) < 200) {
        $fallbackReason = 'Origin returned HTTP '.$direct['status'].' or too little readable content.';
        $readerUrl = 'https://r.jina.ai/http'.(parse_url($url, PHP_URL_SCHEME) === 'https' ? 's' : '').'://'
            .parse_url($url, PHP_URL_HOST)
            .(parse_url($url, PHP_URL_PORT) ? ':'.parse_url($url, PHP_URL_PORT) : '')
            .(parse_url($url, PHP_URL_PATH) ?: '/')
            .(parse_url($url, PHP_URL_QUERY) ? '?'.parse_url($url, PHP_URL_QUERY) : '');
        $fallback = request($readerUrl);
        if ($fallback['status'] >= 200 && $fallback['status'] < 300 && strlen(trim($fallback['body'])) >= 200) {
            $selected = $fallback;
            $via = 'jina_text_reader';
        }
    }

    $text = extractText($selected['body'], $selected['content_type']);
    $title = extractTitle($selected['body'], $selected['content_type']);
    $usable = $selected['status'] >= 200
        && $selected['status'] < 300
        && strlen(trim($text)) >= 200
        && ! looksBlocked($title, $text);
    $packet = [
        'requested_url' => $url,
        'retrieved_on' => gmdate('Y-m-d'),
        'retrieved_at' => gmdate(DATE_ATOM),
        'via' => $via,
        'origin_http_status' => $direct['status'],
        'http_status' => $selected['status'],
        'content_type' => $selected['content_type'],
        'final_url' => $selected['final_url'],
        'page_title' => $title,
        'usable' => $usable,
        'content_sha256' => hash('sha256', $selected['body']),
        'text' => mb_substr($text, 0, 20000),
        'fallback_reason' => $fallbackReason,
        'verification_note' => ! $usable
            ? 'The result is still blocked or unreadable. Find an official mirror, document repository or corroborating source; do not cite this packet as proof.'
            : ($via === 'direct'
                ? 'Direct origin fetch. Confirm that the page supports each claim.'
                : 'Third-party text rendering of the named origin. Preserve the origin URL and corroborate time-sensitive claims.'),
    ];

    echo json_encode($packet, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
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
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/json,text/plain;q=0.9,*/*;q=0.5'],
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
    if (str_contains(strtolower($contentType), 'html')) {
        if (class_exists(DOMDocument::class)) {
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML($body, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
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
    ] as $marker) {
        if (str_contains($sample, $marker)) {
            return true;
        }
    }

    return false;
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

function readJson(string $path): array
{
    if (! file_exists($path)) {
        throw new RuntimeException("Required file does not exist: {$path}");
    }

    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

function writeJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    file_put_contents($path, $json.PHP_EOL);
}
