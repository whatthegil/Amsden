<?php

namespace Tests\Unit;

use App\Services\SimilarityService;
use PHPUnit\Framework\TestCase;

class SimilarityFromTextTest extends TestCase
{
    private array $bluebook = [
        'title'    => 'Development of a Web-Based Attendance Monitoring System Using QR Codes',
        'keywords' => ['Attendance', 'QR Code', 'Web System'],
        'abstract' => 'This study developed a web-based system that records student attendance through QR code scanning.',
        'ocrText'  => 'The proponents built a Laravel web application that scans QR codes to log attendance for each class session.',
    ];

    public function test_a_near_duplicate_proposal_scores_high(): void
    {
        $proposal = 'Chapter 1. This capstone proposes the development of a web-based attendance '
            . 'monitoring system using QR codes. Students will scan a QR code to record their '
            . 'attendance in each class session through a Laravel web application.';

        $score = SimilarityService::computeSimilarityFromText($proposal, $this->bluebook);

        $this->assertGreaterThan(0.5, $score);
    }

    public function test_an_unrelated_proposal_scores_low(): void
    {
        $proposal = 'This capstone proposes an internet-of-things soil moisture sensor network '
            . 'for precision irrigation of rice paddies, transmitting readings over LoRa radio '
            . 'to a solar-powered gateway.';

        $score = SimilarityService::computeSimilarityFromText($proposal, $this->bluebook);

        $this->assertLessThan(0.3, $score);
    }

    public function test_empty_proposal_text_scores_zero(): void
    {
        $this->assertSame(0.0, SimilarityService::computeSimilarityFromText('', $this->bluebook));
    }
}
