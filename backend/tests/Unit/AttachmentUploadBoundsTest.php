<?php

namespace Tests\Unit;

use App\Data\AttachmentUploadData;
use App\Domain\DomainError;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** 超大上傳必須在存取檔案內容前拒絕, 不讓MIME辨識消耗資源 */
class AttachmentUploadBoundsTest extends TestCase
{
    /**
     * 不存在的路徑若被送進finfo會出warning, 此案例只允許輸入錯誤
     *
     * @return void
     */
    public function test_oversized_upload_is_rejected_before_opening_its_content(): void
    {
        config(['attachments.max_bytes' => 1024, 'attachments.general_max_bytes' => 2048]);
        $file = $this->createStub(UploadedFile::class);
        $file->method('getRealPath')->willReturn('/synthetic/oversized-must-not-be-opened');
        $file->method('getSize')->willReturn(2049);
        $file->method('isValid')->willReturn(true);

        $this->expectException(DomainError::class);
        AttachmentUploadData::from($file, '00000000-0000-4000-8000-000000000001');
    }
}
