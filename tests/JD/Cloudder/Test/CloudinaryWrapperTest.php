<?php

namespace JD\Cloudder\Test;

use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\ApiResponse;
use Cloudinary\Api\Exception\ApiError;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Asset\Image;
use Cloudinary\Cloudinary;
use Illuminate\Config\Repository;
use JD\Cloudder\CloudinaryWrapper;
use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * Class CloudinaryWrapperTest
 *
 * @package JD\Cloudder\Test
 */
class CloudinaryWrapperTest extends TestCase
{
    /**
     * @var m\MockInterface
     */
    private $config;
    /**
     * @var m\MockInterface
     */
    private $cloudinary;
    /**
     * @var m\MockInterface
     */
    private $uploader;
    /**
     * @var m\MockInterface
     */
    private $api;
    /**
     * @var CloudinaryWrapper
     */
    private $cloudinary_wrapper;
    /**
     * @var ApiResponse|m\LegacyMockInterface|m\MockInterface
     */
    private $mockResponse;

    public function setUp(): void
    {
        $this->config       = m::mock(Repository::class);
        $this->cloudinary   = m::mock(Cloudinary::class);
        $this->uploader     = m::mock(UploadApi::class);
        $this->api          = m::mock(AdminApi::class);
        $this->mockResponse = m::mock(ApiResponse::class);

//        $this->cloudinary->shouldReceive('uploadApi')->andReturn($this->uploader);
//        $this->cloudinary->shouldReceive('adminApi')->andReturn($this->api);

        $this->config->shouldReceive('get')->once()->with('cloudder.cloudName')->andReturn('cloudName');
        $this->config->shouldReceive('get')->once()->with('cloudder.apiKey')->andReturn('apiKey');
        $this->config->shouldReceive('get')->once()->with('cloudder.apiSecret')->andReturn('apiSecret');

        $this->cloudinary_wrapper = new CloudinaryWrapper($this->config, $this->cloudinary, $this->uploader, $this->api);
    }

    public function tearDown(): void
    {
        // https://github.com/phpspec/prophecy/issues/366#issuecomment-359587114
        $this->addToAssertionCount(
            m::getContainer()->mockery_getExpectationCount()
        );

        m::close();
    }

    /** @test */
    public function it_should_set_uploaded_result_when_uploading_picture()
    {
        // given
        $filename         = 'filename';
        $defaults_options = [
            'public_id' => null,
            'tags'      => []
        ];

        $expected_result = ['public_id' => '123456789'];

        $this->uploader->shouldReceive('upload')->once()
            ->with($filename, $defaults_options)
            ->andReturn($expected_result);

        // when
        $this->cloudinary_wrapper->upload($filename);

        // then
        $result = $this->cloudinary_wrapper->getResult();
        $this->assertEquals($expected_result, $result);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_set_uploaded_result_when_uploading_picture_unsigned()
    {
        // given
        $filename         = 'filename';
        $defaults_options = [
            'public_id' => null,
            'tags'      => []
        ];

        $upload_preset = 'preset';

        $expected_result = ['public_id' => '123456789'];

        $this->uploader->shouldReceive('unsignedUpload')->once()
            ->with($filename, $upload_preset, $defaults_options)
            ->andReturn($expected_result);

        // when
        $this->cloudinary_wrapper->unsignedUpload($filename, null, $upload_preset);

        // then
        $result = $this->cloudinary_wrapper->getResult();
        $this->assertEquals($expected_result, $result);
    }

    /** @test */
    public function it_should_set_uploaded_result_when_uploading_private_picture()
    {
        // given
        $filename         = 'filename';
        $defaults_options = [
            'public_id' => null,
            'tags'      => [],
            'type'      => 'private'
        ];

        $expected_result = ['public_id' => '123456789'];

        $this->uploader->shouldReceive('upload')->once()->with($filename, $defaults_options)->andReturn($expected_result);

        // when
        $this->cloudinary_wrapper->upload($filename, null, ['type' => 'private']);

        // then
        $result = $this->cloudinary_wrapper->getResult();
        $this->assertEquals($expected_result, $result);
    }

    /** @test */
    public function it_should_returns_image_url_when_calling_show()
    {
        // given
        $filename   = 'filename';
        $mock_image = m::mock(Image::class);

        $this->config->shouldReceive('get')->with('cloudder.scaling')->once()->andReturn([]);
//        $this->cloudinary->shouldReceive('cloudinary_url')->once()->with($filename, []);
        $this->cloudinary->shouldReceive('image')->once()->with($filename)->andReturn($mock_image);
        $mock_image->shouldReceive('toUrl')->once()->with([])->andReturn('hi');

        // when
        $this->cloudinary_wrapper->show($filename, []);
    }

    /** @test */
    public function it_should_returns_https_image_url_when_calling_secure_show()
    {
        // given
        $filename   = 'filename';
        $mock_image = m::mock(Image::class);

        $this->config->shouldReceive('get')->with('cloudder.scaling')->once()->andReturn([]);
        $this->cloudinary->shouldReceive('image')->once()->with($filename)->andReturn($mock_image);
        $mock_image->shouldReceive('toUrl')->once()->with(['secure' => TRUE])->andReturn('hi');

        // when
        $this->cloudinary_wrapper->secureShow($filename);
    }

    /** @test */
    public function it_should_returns_image_url_when_calling_show_private_url()
    {
        // given
        $filename   = 'filename';
        $mock_image = m::mock(Image::class);

        $this->cloudinary->shouldReceive('image')->once()->with($filename)->andReturn($mock_image);
        $mock_image->shouldReceive('privateCdn')->once()->with(TRUE)->andReturn($mock_image);
        $mock_image->shouldReceive('toUrl')->once()->with([])->andReturn('hi');
        $mock_image->makePartial();

        // when
        $this->cloudinary_wrapper->showPrivateUrl($filename, 'png');
    }

    /** @test */
    public function it_should_returns_image_url_when_calling_private_download_url()
    {
        // given
        $filename   = 'filename';
        $mock_image = m::mock(Image::class);
        $this->cloudinary->shouldReceive('image')->once()->with($filename)->andReturn($mock_image);
        $mock_image->shouldReceive('privateCdn')->once()->with(TRUE)->andReturn($mock_image);
        $mock_image->shouldReceive('toUrl')->once()->with([])->andReturn('hi');
        $mock_image->makePartial();

        // when
        $this->cloudinary_wrapper->privateDownloadUrl($filename, 'png');
    }

    /** @test */
    public function it_should_call_api_rename_when_calling_rename()
    {
        // given
        $from = 'from';
        $to   = 'to';

        $this->uploader->shouldReceive('rename')->with($from, $to, [])->once();

        // when
        $this->cloudinary_wrapper->rename($from, $to);
    }

    /** @test */
    public function it_should_call_api_destroy_when_calling_destroy_image()
    {
        // given
        $pid = 'pid';
        $this->mockResponse->shouldReceive('getArrayCopy')->andReturn(['result' => 'ok']);
        $this->uploader->shouldReceive('destroy')->with($pid, [])->once()->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->destroyImage($pid);
    }

    /** @test */
    public function it_should_call_api_destroy_when_calling_destroy()
    {
        // given
        $pid = 'pid';
        $this->mockResponse->shouldReceive('getArrayCopy')->andReturn(['result' => 'ok']);
        $this->uploader->shouldReceive('destroy')->with($pid, [])->once()->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->destroy($pid);
    }

    /** @test */
    public function verify_delete_alias_returns_boolean()
    {
        // given
        $pid = 'pid';
        $this->mockResponse->shouldReceive('getArrayCopy')->andReturn(['result' => 'ok']);
        $this->uploader->shouldReceive('destroy')->with($pid, [])->once()->andReturn($this->mockResponse);

        // when
        $deleted = $this->cloudinary_wrapper->delete($pid);
        $this->assertTrue($deleted);
    }

    /** @test */
    public function it_should_call_api_add_tag_when_calling_add_tag()
    {
        $pids = ['pid1', 'pid2'];
        $tag  = 'tag';

        $this->uploader->shouldReceive('addTag')->once()->with($tag, $pids, []);

        $this->cloudinary_wrapper->addTag($tag, $pids);
    }

    /** @test */
    public function it_should_call_api_remove_tag_when_calling_add_tag()
    {
        $pids = ['pid1', 'pid2'];
        $tag  = 'tag';

        $this->uploader->shouldReceive('removeTag')->once()->with($tag, $pids, [])->andReturn($this->mockResponse);

        $this->cloudinary_wrapper->removeTag($tag, $pids);
    }

    /** @test */
    public function it_should_call_api_rename_tag_when_calling_add_tag()
    {
        $pids = ['pid1', 'pid2'];
        $tag  = 'tag';

        $this->uploader->shouldReceive('replaceTag')->once()->with($tag, $pids, [])->andReturn($this->mockResponse);

        $this->cloudinary_wrapper->replaceTag($tag, $pids);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_call_api_delete_resources_when_calling_destroy_images()
    {
        $pids = ['pid1', 'pid2'];
        $this->api->shouldReceive('deleteAssets')->once()->with($pids, []);

        $this->cloudinary_wrapper->destroyImages($pids);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_call_api_delete_resources_when_calling_delete_resources()
    {
        $pids = ['pid1', 'pid2'];
        $this->api->shouldReceive('deleteAssets')->once()->with($pids, []);

        $this->cloudinary_wrapper->deleteResources($pids);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_call_api_delete_resources_by_prefix_when_calling_delete_resources_by_prefix()
    {
        $prefix = 'prefix';
        $this->api->shouldReceive('deleteAssetsByPrefix')->once()->with($prefix, []);

        $this->cloudinary_wrapper->deleteResourcesByPrefix($prefix);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_call_api_delete_all_resources_when_calling_delete_all_resources()
    {
        $this->api->shouldReceive('deleteAllAssets')->once()->with([]);

        $this->cloudinary_wrapper->deleteAllResources();
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_call_api_delete_resources_by_tag_when_calling_delete_resources_by_tag()
    {
        $tag = 'tag1';
        $this->api->shouldReceive('deleteAssetsByTag')->once()->with($tag, []);

        $this->cloudinary_wrapper->deleteResourcesByTag($tag);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_call_api_delete_derived_resources_when_calling_delete_derived_resources()
    {
        $pids = ['pid1', 'pid2'];
        $this->api->shouldReceive('deleteDerivedAssets')->once()->with($pids);

        $this->cloudinary_wrapper->deleteDerivedResources($pids);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_set_uploaded_result_when_uploading_video()
    {
        // given
        $filename         = 'filename';
        $defaults_options = [
            'public_id'     => null,
            'tags'          => [],
            'resource_type' => 'video'
        ];

        $expected_result = ['public_id' => '123456789'];

        $this->uploader->shouldReceive('upload')->once()
            ->with($filename, $defaults_options)
            ->andReturn($expected_result);

        // when
        $this->cloudinary_wrapper->uploadVideo($filename);

        // then
        $result = $this->cloudinary_wrapper->getResult();
        $this->assertEquals($expected_result, $result);
    }

    /** @test */
    public function it_should_call_api_create_archive_when_generating_archive()
    {
        // given
        $this->uploader->shouldReceive('createArchive')->once()->with(
            ['tag' => 'kitten', 'mode' => 'create', 'target_public_id' => null]
        );

        // when
        $this->cloudinary_wrapper->createArchive(['tag' => 'kitten']);
    }

    /** @test */
    public function it_should_call_api_create_archive_with_correct_archive_name()
    {
        // given
        $this->uploader->shouldReceive('createArchive')->once()->with(
            ['tag' => 'kitten', 'mode' => 'create', 'target_public_id' => 'kitten_archive']
        );

        // when
        $this->cloudinary_wrapper->createArchive(['tag' => 'kitten'], 'kitten_archive');
    }

    /** @test */
    public function it_should_call_api_download_archive_url_when_generating_archive()
    {
        // given
        $this->uploader->shouldReceive('downloadArchiveUrl')->once()->with(
            ['tag' => 'kitten', 'target_public_id' => null]
        );

        // when
        $this->cloudinary_wrapper->downloadArchiveUrl(['tag' => 'kitten']);
    }

    /** @test */
    public function it_should_call_api_download_archive_url_with_correct_archive_name()
    {
        // given
        $this->uploader->shouldReceive('downloadArchiveUrl')->once()->with(
            ['tag' => 'kitten', 'target_public_id' => 'kitten_archive']
        );

        // when
        $this->cloudinary_wrapper->downloadArchiveUrl(['tag' => 'kitten'], 'kitten_archive');
    }

    /** @test */
    public function it_should_show_response_when_calling_resources()
    {
        // given
        $this->api->shouldReceive('assets')->once()->with([])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->resources();
    }

    /** @test */
    public function it_should_show_response_when_calling_resources_by_ids()
    {
        $pids = ['pid1', 'pid2'];

        $options = ['test', 'test1'];

        // given
        $this->api->shouldReceive('assetsByIds')->once()->with($pids, $options)->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->resourcesByIds($pids, $options);
    }

    /** @test */
    public function it_should_show_response_when_calling_resources_by_tag()
    {
        $tag = 'tag';

        // given
        $this->api->shouldReceive('assetsByTag')->once()->with($tag, [])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->resourcesByTag($tag);
    }

    /** @test */
    public function it_should_show_response_when_calling_resources_by_moderation()
    {
        $kind   = 'manual';
        $status = 'pending';

        // given
        $this->api->shouldReceive('assetsByModeration')->once()->with($kind, $status, [])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->resourcesByModeration($kind, $status);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_show_list_when_calling_tags()
    {
        // given
        $this->api->shouldReceive('tags')->once()->with([])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->tags();
    }

    /** @test */
    public function it_should_show_response_when_calling_resource()
    {
        $pid = 'pid';

        // given
        $this->api->shouldReceive('asset')->once()->with($pid, [])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->resource($pid);
    }

    /** @test */
    public function it_should_update_a_resource_when_calling_update()
    {
        $pid     = 'pid';
        $options = ['tags' => 'tag1'];

        // given
        $this->api->shouldReceive('update')->once()->with($pid, $options)->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->update($pid, $options);
    }

    /** @test */
    public function it_should_show_transformations_list_when_calling_transformations()
    {
        // given
        $this->api->shouldReceive('transformations')->once()->with([])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->transformations();
    }

    /** @test */
    public function it_should_show_one_transformation_when_calling_transformation()
    {
        $transformation = "c_fill,h_100,w_150";

        // given
        $this->api->shouldReceive('transformation')->once()->with($transformation, [])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->transformation($transformation);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_delete_a_transformation_when_calling_delete_transformation()
    {
        $transformation = "c_fill,h_100,w_150";

        // given
        $this->api->shouldReceive('deleteTransformation')->once()->with($transformation, [])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->deleteTransformation($transformation);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_update_a_transformation_when_calling_update_transformation()
    {
        $transformation = "c_fill,h_100,w_150";
        $updates        = ["allowed_for_strict" => 1];

        // given
        $this->api->shouldReceive('updateTransformation')->once()->with($transformation, $updates)->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->updateTransformation($transformation, $updates);
    }

    /** @test */
    public function it_should_create_a_transformation_when_calling_create_transformation()
    {
        $name       = "name";
        $definition = "c_fill,h_100,w_150";

        // given
        $this->api->shouldReceive('createTransformation')->once()->with($name, $definition)->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->createTransformation($name, $definition);
    }

    /** @test */
    public function it_should_restore_resources_when_calling_restore()
    {
        $pids = ['pid1', 'pid2'];

        // given
        $this->api->shouldReceive('restore')->once()->with($pids, []);

        // when
        $this->cloudinary_wrapper->restore($pids);
    }

    /** @test */
    public function it_should_show_upload_mappings_list_when_calling_upload_mappings()
    {
        // given
        $this->api->shouldReceive('uploadMappings')->once()->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->uploadMappings();
    }

    /** @test */
    public function it_should_upload_mapping_when_calling_upload_mapping()
    {
        $pid = 'pid1';

        // given
        $this->api->shouldReceive('uploadMapping')->once()->with($pid)->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->uploadMapping($pid);
    }

    /** @test */
    public function it_should_create_upload_mapping_when_calling_create_upload_mapping()
    {
        $pid = 'pid1';

        // given
        $this->api->shouldReceive('createUploadMapping')->once()->with($pid, [])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->createUploadMapping($pid);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_delete_upload_mapping_when_calling_delete_upload_mapping()
    {
        $pid = 'pid1';

        // given
        $this->api->shouldReceive('deleteUploadMapping')->once()->with($pid)->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->deleteUploadMapping($pid);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_update_upload_mapping_when_calling_update_upload_mapping()
    {
        $pid = 'pid1';

        // given
        $this->api->shouldReceive('updateUploadMapping')->once()->with($pid, [])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->updateUploadMapping($pid);
    }

    /** @test */
    public function it_should_show_upload_presets_list_when_calling_upload_presets()
    {
        // given
        $this->api->shouldReceive('uploadPresets')->once()->with([])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->uploadPresets();
    }


    /** @test */
    public function it_should_upload_preset_when_calling_upload_preset()
    {
        $pid = 'pid1';

        // given
        $this->api->shouldReceive('uploadPreset')->once()->with($pid, [])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->uploadPreset($pid);
    }

    /** @test */
    public function it_should_create_upload_preset_when_calling_create_upload_preset()
    {
        $pid = 'pid1';

        // given
        $this->api->shouldReceive('createUploadPreset')->once()->with($pid)->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->createUploadPreset($pid);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_delete_upload_preset_when_calling_delete_upload_preset()
    {
        $pid = 'pid1';

        // given
        $this->api->shouldReceive('deleteUploadPreset')->once()->with($pid)->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->deleteUploadPreset($pid);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_update_upload_preset_when_calling_update_upload_preset()
    {
        $pid = 'pid1';

        // given
        $this->api->shouldReceive('updateUploadPreset')->once()->with($pid, [])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->updateUploadPreset($pid);
    }

    /** @test */
    public function it_should_show_root_folders_list_when_calling_root_folders()
    {
        // given
        $this->api->shouldReceive('rootFolders')->once()->with([])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->rootFolders();
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_subfolders_when_calling_subfolders()
    {
        $pid = 'pid1';

        // given
        $this->api->shouldReceive('subfolders')->once()->with($pid, [])->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->subfolders($pid);
    }

    /** @test
     * @throws ApiError
     */
    public function it_should_show_usage_list_when_calling_usage()
    {
        // given
        $this->api->shouldReceive('usage')->once()->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->usage();
    }

    /** @test */
    public function it_should_show_ping_list_when_calling_ping()
    {
        // given
        $this->api->shouldReceive('ping')->once()->andReturn($this->mockResponse);

        // when
        $this->cloudinary_wrapper->ping();
    }
}
