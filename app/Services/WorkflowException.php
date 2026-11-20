<?php

namespace App\Services;

use RuntimeException;

/** Pelanggaran aturan alur kerja (ditampilkan ke pengguna sebagai pesan). code 409 = konflik versi, 422 = validasi, 403 = larangan. */
class WorkflowException extends RuntimeException
{
    public static function conflict(): self
    {
        return new self('Data telah diubah pengguna lain. Muat ulang halaman lalu ulangi.', 409);
    }

    public static function forbidden(string $msg = 'Anda tidak berwenang melakukan aksi ini.'): self
    {
        return new self($msg, 403);
    }

    public static function invalid(string $msg): self
    {
        return new self($msg, 422);
    }
}
