<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Laporan teknisi belum bisa dikirim (unit belum lengkap) atau order tidak sedang dikerjakan.
 */
class ReportIncompleteException extends RuntimeException {}
