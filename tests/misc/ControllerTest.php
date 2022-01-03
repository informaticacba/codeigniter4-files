<?php

use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Tatter\Files\Controllers\Files;
use Tatter\Files\Models\FileModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class ControllerTest extends TestCase
{
    use ControllerTestTrait;
    use DatabaseTestTrait;

    /**
     * Our Controller set by the trait
     *
     * @var Files|null
     */
    protected $controller;

    protected $refreshVfs = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller(Files::class);
    }

    public function testSetPreferencesUsesValidInput()
    {
        $_REQUEST = [
            'sort'    => 'size',
            'order'   => 'desc',
            'format'  => 'list',
            'perPage' => '42',
        ];

        $method = $this->getPrivateMethodInvoker($this->controller, 'setPreferences');
        $result = $method();

        $this->assertSame('size', preference('Files.sort'));
        $this->assertSame('desc', preference('Files.order'));
        $this->assertSame('list', preference('Files.format'));
        $this->assertSame('42', preference('Pager.perPage'));
    }

    public function testSetPreferencesIgnoreInvalidInput()
    {
        $config   = config('Files');
        $_REQUEST = [
            'sort'    => 'potato',
            'order'   => 'up',
            'format'  => 'banana',
            'perPage' => '-10',
        ];

        $method = $this->getPrivateMethodInvoker($this->controller, 'setPreferences');
        $result = $method();

        $this->assertSame($config->sort, preference('Files.sort'));
        $this->assertSame($config->order, preference('Files.order'));
        $this->assertSame($config->format, preference('Files.format'));
        $this->assertSame(config('Pager')->perPage, preference('Pager.perPage'));
    }

    public function testDataUsesVarWithFaker()
    {
        $file = fake(FileModel::class);

        $method = $this->getPrivateMethodInvoker($this->controller, 'setData');
        $method([
            'files' => [
                0 => $file,
            ],
        ]);

        $result = $this->controller->display();
        $this->assertStringContainsString($file->filename, $result);
    }

    public function testExportCreatesRecord()
    {
        $file = fake(FileModel::class, [
            'localname' => 'image.jpg',
        ]);

        $this->controller(Files::class);
        $result = $this->execute('export', 'download', $file->id);

        $this->assertInstanceOf(DownloadResponse::class, $result->response());
        $this->seeInDatabase('exports', ['file_id' => $file->id]);
    }
}
