<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AccessRequest;
use App\Models\Bluebook;
use App\Services\Pdf\PageImages;

/**
 * Sends page $n of what a reader may see, as an image with an email and the
 * crest drawn into it. Shared by the student route and the admin's "preview as
 * a reader", so the preview cannot drift from what students are actually sent.
 *
 * $n counts the reader's pages, not the file's: a partial waiver's pages run 1,
 * 2, 3 however far apart they sit in the thesis, so the numbers give nothing
 * away about what is withheld.
 */
trait ServesPageImages
{
    private function servePageImage(int $id, int $n, string $email, bool $onlyIfPosted)
    {
        // Only what the access check needs. This runs once per page, and the
        // full record carries the paper's extracted text - a hundred kilobytes
        // or more, read and thrown away for every page of every reader.
        $row = Bluebook::query()
            ->select(['id', 'status', 'file_path', 'access_level', 'access_parts', 'withheld_pages', 'page_images_count', 'page_images_source'])
            ->find($id);
        $bluebook = $row ? [
            'status'           => $row->status,
            'filePath'         => $row->file_path,
            'accessLevel'      => $row->access_level ?: Bluebook::ACCESS_PUBLIC,
            'accessParts'      => $row->access_parts ?? [],
            'withheldPages'    => $row->withheld_pages,
            'pageImagesCount'  => (int) ($row->page_images_count ?? 0),
            'pageImagesSource' => $row->page_images_source,
        ] : null;

        if (!$bluebook || !$bluebook['filePath'] || !PageImages::ready($bluebook)
            || ($onlyIfPosted && $bluebook['status'] !== 'Approved')) {
            abort(404);
        }

        // A reader the library has granted the full text sees every page but
        // the withheld ones. The admin's preview shows what readers get
        // without a grant.
        $bluebook['granted'] = $onlyIfPosted && AccessRequest::granted($id, $email);

        if ($bluebook['accessLevel'] === Bluebook::ACCESS_CONSULTATION && !$bluebook['granted']) {
            abort(403, 'This bluebook is restricted. It is available only with the author\'s authorization.');
        }

        $pages = PageImages::visiblePages($bluebook);
        if ($n < 1 || $n > count($pages)) {
            abort(404);
        }

        $stored = PageImages::disk()->get(PageImages::path($id, $pages[$n - 1]));
        if ($stored === null) {
            abort(404);
        }

        return response(PageImages::watermark($stored, $email), 200, [
            'Content-Type'           => 'image/jpeg',
            // Marked for this reader, so never shared by a cache between readers.
            // Kept a day: the viewer's page addresses carry a version naming the
            // reader, the file and the waiver (PageImages::version), so a change
            // to any of them is a new address rather than a stale page.
            'Cache-Control'          => 'private, max-age=86400, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
