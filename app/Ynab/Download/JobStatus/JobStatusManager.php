<?php

declare(strict_types=1);

namespace App\Ynab\Download\JobStatus;

use App\Services\Session\Constants;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;

/**
 * Class JobStatusManager.
 */
class JobStatusManager
{
    /**
     * @param string $downloadIdentifier
     * @param int    $index
     * @param string $error
     */
    public static function addError(string $downloadIdentifier, int $index, string $error): void
    {
        $disk = Storage::disk('jobs');
        try {
            if ($disk->exists($downloadIdentifier)) {
                $status                   = JobStatus::fromArray(json_decode($disk->get($downloadIdentifier), true, 512, JSON_THROW_ON_ERROR));
                $status->errors[$index]   = $status->errors[$index] ?? [];
                $status->errors[$index][] = $error;
                self::storeJobStatus($downloadIdentifier, $status);
            }
        } catch (FileNotFoundException $e) {
            app('log')->error($e->getMessage());
            app('log')->error($e->getTraceAsString());
        }
    }

    /**
     * @param string $downloadIdentifier
     * @param int    $index
     * @param string $message
     */
    public static function addMessage(string $downloadIdentifier, int $index, string $message): void
    {
        $disk = Storage::disk('jobs');
        try {
            if ($disk->exists($downloadIdentifier)) {
                $status                     = JobStatus::fromArray(json_decode($disk->get($downloadIdentifier), true, 512, JSON_THROW_ON_ERROR));
                $status->messages[$index]   = $status->messages[$index] ?? [];
                $status->messages[$index][] = $message;
                self::storeJobStatus($downloadIdentifier, $status);
            }
        } catch (FileNotFoundException $e) {
            app('log')->error($e->getMessage());
            app('log')->error($e->getTraceAsString());
        }
    }

    /**
     * @param string $downloadIdentifier
     * @param int    $index
     * @param string $warning
     */
    public static function addWarning(string $downloadIdentifier, int $index, string $warning): void
    {
        $disk = Storage::disk('jobs');
        try {
            if ($disk->exists($downloadIdentifier)) {
                $status                     = JobStatus::fromArray(json_decode($disk->get($downloadIdentifier), true, 512, JSON_THROW_ON_ERROR));
                $status->warnings[$index]   = $status->warnings[$index] ?? [];
                $status->warnings[$index][] = $warning;
                self::storeJobStatus($downloadIdentifier, $status);
            }
        } catch (FileNotFoundException $e) {
            app('log')->error($e->getMessage());
            app('log')->error($e->getTraceAsString());
        }
    }

    /**
     * @param string $status
     *
     * @return JobStatus
     */
    public static function setJobStatus(string $status): JobStatus
    {
        $downloadIdentifier = session()->get(Constants::DOWNLOAD_JOB_IDENTIFIER);
        app('log')->debug(sprintf('Now in download setJobStatus(%s) for job %s', $status, $downloadIdentifier));

        $jobStatus         = self::startOrFindJob($downloadIdentifier);
        $jobStatus->status = $status;

        self::storeJobStatus($downloadIdentifier, $jobStatus);

        return $jobStatus;
    }

    /**
     * @param string $downloadIdentifier
     *
     * @return JobStatus
     */
    public static function startOrFindJob(string $downloadIdentifier): JobStatus
    {
        //app('log')->debug(sprintf('Now in (download) startOrFindJob(%s)', $downloadIdentifier));
        $disk = Storage::disk('jobs');
        try {
            //app('log')->debug(sprintf('Try to see if file exists for download job %s.', $downloadIdentifier));
            if ($disk->exists($downloadIdentifier)) {
                //app('log')->debug(sprintf('Status file exists for download job %s.', $downloadIdentifier));
                $array  = json_decode($disk->get($downloadIdentifier), true, 512, JSON_THROW_ON_ERROR);
                $status = JobStatus::fromArray($array);

                //app('log')->debug(sprintf('Status found for download job %s', $downloadIdentifier), $array);

                return $status;
            }
        } catch (FileNotFoundException $e) {
            app('log')->error('Could not find download job file, write a new one.');
            app('log')->error($e->getMessage());
        }
        app('log')->debug('Download job file does not exist or error, create a new one.');
        $status = new JobStatus;
        $disk->put($downloadIdentifier, json_encode($status->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        app('log')->debug('Return download job status.', $status->toArray());

        return $status;
    }

    /**
     * @param string    $downloadIdentifier
     * @param JobStatus $status
     */
    private static function storeJobStatus(string $downloadIdentifier, JobStatus $status): void
    {
        app('log')->debug(sprintf('Now in storeJobStatus(%s): %s', $downloadIdentifier, $status->status));
        $disk = Storage::disk('jobs');
        $disk->put($downloadIdentifier, json_encode($status->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }
}