<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Pdf\PdfWatermarker;
use App\Services\Store;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Sends a bluebook's PDF to the person reading it: logged, stamped with who
 * asked for it, and streamed. Shared by the reader's route and the admin's, which
 * differ only in what they allow through before this point.
 */
trait StreamsBluebookDocument
{
    /**
     * @param string|null $partial A local copy already cut down to the pages the
     *                             waiver permits, or null to send the stored file.
     */
    private function streamBluebookDocument(array $bluebook, array $user, Filesystem $disk, ?string $partial)
    {
        // The bytes leaving, recorded separately from the page being opened.
        // Until now only the page was logged, so a request straight to this
        // route - a script with a session cookie, or a reader saving the file -
        // left nothing behind at all, and the log read as though the document
        // had only ever been looked at in the viewer.
        Store::addLog([
            'userName' => $user['name'] ?? 'Unknown',
            'email'    => $user['email'] ?? 'Unknown',
            'action'   => 'Downloaded Bluebook File',
            'document' => $bluebook['title'],
        ]);

        // The document is streamed rather than handed to Storage::response().
        // That helper sets Content-Length from the object's recorded size and
        // then streams the body separately; when the two disagree nginx aborts
        // the response mid-flight with
        //
        //     upstream sent more data than specified in "Content-Length" header
        //
        // and the browser gets a 503 instead of a PDF. Letting the response go
        // out chunked, with no declared length, removes the disagreement.
        // The stored file carries where the document came from. This adds who
        // asked for it, so a copy that leaves here names the account it left
        // with. Best effort: a host with no MuPDF binary, or a document that
        // will not stamp, serves the stored file rather than nothing - the
        // provenance mark is still on it, and the viewer still draws the
        // reader's identity over every page it renders.
        $temp = null;

        if ($partial !== null) {
            // Already a local copy, cut down to the permitted pages; stamp that
            // rather than the whole stored file, and serve it unstamped if the
            // stamp will not take.
            $temp = $partial;

            if (config('watermark.per_viewer', true)) {
                $lines   = PdfWatermarker::viewerLines($user);
                $stamped = $partial . '.stamped.pdf';

                if (PdfWatermarker::stampFile($partial, $stamped, $lines[0], $lines[1], PdfWatermarker::VIEWER_OFFSET, true)) {
                    @unlink($partial);
                    $temp = $stamped;
                }
            }
        } elseif (config('watermark.per_viewer', true)) {
            $lines = PdfWatermarker::viewerLines($user);
            $temp  = PdfWatermarker::stampToTemp(
                $bluebook['filePath'], $lines[0], $lines[1], PdfWatermarker::VIEWER_OFFSET, true
            );
        }

        $stream = $temp !== null
            ? @fopen($temp, 'rb')
            : $disk->readStream($bluebook['filePath']);

        if ($stream === false || $stream === null) {
            if ($temp !== null) {
                @unlink($temp);
            }
            abort(404);
        }

        return response()->stream(function () use ($stream, $temp) {
            // These documents run to tens of megabytes, so on a slow connection
            // the send outlives the default execution limit. Hitting it mid-file
            // truncates the response, and the viewer is handed a PDF that ends
            // in the middle of the byte stream.
            @set_time_limit(0);

            // Flushed in chunks so the first bytes reach the viewer promptly on
            // a document that runs to tens of megabytes, rather than the whole
            // file being buffered before anything is sent.
            while (!feof($stream)) {
                $chunk = fread($stream, 262144);
                if ($chunk === false) {
                    break;
                }
                echo $chunk;

                // flush() alone only pushes the web server's buffer. With PHP's
                // own output buffering on, the bytes are still sitting in it.
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            }
            fclose($stream);

            // The stamped copy exists only for this response. Dropped here
            // rather than on a schedule, because it is a whole document and
            // there is one per read.
            if ($temp !== null) {
                @unlink($temp);
            }
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($bluebook['fileOriginalName'] ?? 'document.pdf') . '"',
            'Cache-Control'       => 'no-store',
            'X-Frame-Options'     => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
