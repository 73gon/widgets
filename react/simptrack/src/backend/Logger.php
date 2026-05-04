<?php
/**
 * =============================================================================
 * Logger — Simptrack diagnostic logger
 * =============================================================================
 *
 * JSON-line structured logging to a daily-rotated file.
 *   - daily rotation (file name = simptrack-YYYY-MM-DD.log)
 *   - size cap per file (default 5 MB) — rotates to .1, .2, ...
 *   - retention (default 14 days) — older files purged on next write
 *
 * Configured via $CONFIG['LOG_DIR'] (default src/backend/logs/).
 * Never throws — logging failures degrade silently.
 */

class Logger
  {
  private const MAX_BYTES = 5 * 1024 * 1024;
  private const RETENTION_DAYS = 14;

  private static ?string $logDir = null;

  public static function configure(string $logDir): void
    {
    self::$logDir = rtrim($logDir, DIRECTORY_SEPARATOR);
    }

  public static function error(string $context, array $data = []): void
    {
    self::write('ERROR', $context, $data);
    }

  public static function warn(string $context, array $data = []): void
    {
    self::write('WARN', $context, $data);
    }

  public static function info(string $context, array $data = []): void
    {
    self::write('INFO', $context, $data);
    }

  public static function debug(string $context, array $data = []): void
    {
    self::write('DEBUG', $context, $data);
    }

  /**
   * Returns elapsed milliseconds since $startTime (from microtime(true)).
   * Use: $t = microtime(true); …work…; $ms = Logger::timeMs($t);
   */
  public static function timeMs(float $startTime): float
    {
    return round((microtime(true) - $startTime) * 1000, 2);
    }

  private static function write(string $level, string $context, array $data): void
    {
    try {
      $dir = self::$logDir ?? __DIR__ . '/logs';
      if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        if (!is_dir($dir))
          return;
        }

      $file = $dir . DIRECTORY_SEPARATOR . 'simptrack-' . date('Y-m-d') . '.log';
      self::rotateIfNeeded($file);
      self::purgeOld($dir);

      $entry = [
        'ts' => date('c'),
        'level' => $level,
        'context' => $context,
        'data' => $data,
      ];
      @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
      } catch (\Throwable $e) {
      // logging must never throw
      }
    }

  private static function rotateIfNeeded(string $file): void
    {
    if (!is_file($file))
      return;
    if (@filesize($file) < self::MAX_BYTES)
      return;

    // shift existing rotated files: .(n-1) -> .n
    for ($i = 9; $i >= 1; $i--) {
      $older = $file . '.' . $i;
      $newer = $file . '.' . ($i + 1);
      if (is_file($older))
        @rename($older, $newer);
      }
    @rename($file, $file . '.1');
    }

  private static function purgeOld(string $dir): void
    {
    $cutoff = time() - (self::RETENTION_DAYS * 86400);
    $files = @glob($dir . DIRECTORY_SEPARATOR . 'simptrack-*.log*') ?: [];
    foreach ($files as $f) {
      $mtime = @filemtime($f);
      if ($mtime !== false && $mtime < $cutoff)
        @unlink($f);
      }
    }
  }
