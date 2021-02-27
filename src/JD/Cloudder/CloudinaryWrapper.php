<?php

namespace JD\Cloudder;

use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\ApiResponse;
use Cloudinary\Api\Exception\ApiError;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use Exception;
use Illuminate\Config\Repository;

/**
 * Class CloudinaryWrapper
 *
 * @package JD\Cloudder
 */
class CloudinaryWrapper
{
    /**
     * Reference to the Cloudinary library.
     *
     * @var Cloudinary
     */
    protected $cloudinary;

    /**
     * Reference to the Cloudinary uploader.
     *
     * @var UploadApi
     */
    protected $uploader;

    /**
     * Reference to the Repository config.
     *
     * @var Repository
     */
    protected $config;

    /**
     * Reference to the Uploaded result.
     *
     * @var array
     */
    protected $uploadedResult;

    /**
     * Reference to the Cloudinary [Admin] API.
     *
     * @var AdminApi
     */
    private $api;

    /**
     * Create a new cloudinary instance.
     *
     *
     * @param Repository $config
     * @param Cloudinary $cloudinary
     * @param UploadApi  $uploader
     * @param AdminApi   $api
     */
    public function __construct(Repository $config, Cloudinary $cloudinary, UploadApi $uploader, AdminApi $api)
    {
        $this->cloudinary = $cloudinary;
        $this->uploader   = $uploader;
        $this->api        = $api;
        $this->config     = $config;

        // Configure Cloudinary.
        $this->cloudinary->configuration = [
            'cloud' => [
                'cloud_name' => $this->config->get('cloudder.cloudName'),
                'api_key'    => $this->config->get('cloudder.apiKey'),
                'api_secret' => $this->config->get('cloudder.apiSecret')
            ]
        ];
    }

    /**
     * Returns the Cloudinary instance.
     *
     * @return Cloudinary
     */
    public function getCloudinary(): Cloudinary
    {
        return $this->cloudinary;
    }

    /**
     * Returns the Cloudinary UploadAPI instance.
     *
     * @return UploadApi
     */
    public function getUploader(): UploadApi
    {
        return $this->uploader;
    }

    /**
     * Returns the Cloudinary AdminAPI instance.
     *
     * @return AdminApi
     */
    public function getApi(): AdminApi
    {
        return $this->api;
    }

    /**
     * Upload an image to cloud storage.
     *
     * @param mixed  $source
     * @param string $publicId
     * @param array  $uploadOptions
     * @param array  $tags
     * @return CloudinaryWrapper
     * @throws ApiError
     */
    public function upload($source, $publicId = null, array $uploadOptions = [], array $tags = []): CloudinaryWrapper
    {
        $defaults = [
            'public_id' => null,
            'tags'      => []
        ];
        $options  = array_merge($defaults, [
            'public_id' => $publicId,
            'tags'      => $tags
        ], $uploadOptions);

        $this->uploadedResult = $this->getUploader()->upload($source, $options);

        return $this;
    }

    /**
     * Upload an image to cloud storage using the unsigned method.
     *
     * @param mixed       $source
     * @param null        $publicId
     * @param string|null $uploadPreset
     * @param array       $uploadOptions
     * @param array       $tags
     * @return CloudinaryWrapper
     * @throws ApiError
     */
    public function unsignedUpload($source, $publicId = null, string $uploadPreset = null,
                                   array $uploadOptions = [], array $tags = []): CloudinaryWrapper
    {
        $defaults = [
            'public_id' => null,
            'tags'      => []
        ];

        $options = array_merge($defaults, [
            'public_id' => $publicId,
            'tags'      => $tags,
        ]);

        $options              = array_merge($options, $uploadOptions);
        $this->uploadedResult = $this->getUploader()->unsignedUpload($source, $uploadPreset, $options);

        return $this;
    }

    /**
     * Upload a video to cloud storage.
     *
     * @param mixed  $source
     * @param string $publicId
     * @param array  $uploadOptions
     * @param array  $tags
     * @return CloudinaryWrapper
     * @throws ApiError
     */
    public function uploadVideo($source, $publicId = null, $uploadOptions = [], $tags = []): CloudinaryWrapper
    {
        $options = array_merge($uploadOptions, ['resource_type' => 'video']);
        return $this->upload($source, $publicId, $options, $tags);
    }

    /**
     * Returns the uploaded result of the most recent upload.
     *
     * @return array
     */
    public function getResult(): array
    {
        return $this->uploadedResult;
    }

    /**
     * Returns the public ID of the most recent upload.
     *
     * @return string
     */
    public function getPublicId(): string
    {
        return $this->uploadedResult['public_id'];
    }

    /**
     * Returns a URL for displaying a resource.
     *
     * @param string $publicId
     * @param array  $options
     * @return string
     */
    public function show(string $publicId, array $options = []): string
    {
        $defaults = $this->config->get('cloudder.scaling');
        $options  = array_merge($defaults, $options);

        return $this->getCloudinary()->image($publicId)->toUrl($options);
    }

    /**
     * Returns a secure (HTTPS) URL for displaying a resource.
     *
     * @param string $publicId
     * @param array  $options
     * @return string
     */
    public function secureShow(string $publicId, $options = []): string
    {
        $defaults = $this->config->get('cloudder.scaling');
        $options  = array_merge($defaults, $options, ['secure' => TRUE]);

        return $this->getCloudinary()->image($publicId)->toUrl($options);
    }

    /**
     * An alias for privateDownloadUrl().
     *
     * @param string $publicId
     * @param string $format
     * @param array  $options
     * @return string
     */
    public function showPrivateUrl(string $publicId, string $format, $options = []): string
    {
        return $this->privateDownloadUrl($publicId, $format, $options);
    }

    /**
     * Returns a private URL for displaying a resource.
     *
     * @param string $publicId
     * @param string $format
     * @param array  $options
     * @return string
     */
    public function privateDownloadUrl(string $publicId, string $format, $options = []): string
    {
        return $this->getCloudinary()
            ->image($publicId)
            ->privateCdn(TRUE)
            ->format($format)
            ->toUrl($options);
    }

    /**
     * Rename a resource's public ID.
     *
     * @param string $publicId
     * @param string $toPublicId
     * @param array  $options
     * @return array|false
     */
    public function rename(string $publicId, string $toPublicId, $options = [])
    {
        try
        {
            return $this->getUploader()->rename($publicId, $toPublicId, $options);
        }
        catch (Exception $e)
        {
            return FALSE;
        }
    }

    /**
     * An alias for destroy().
     *
     * @param string $publicId
     * @param array  $options
     * @return array
     */
    public function destroyImage(string $publicId, $options = []): array
    {
        return $this->destroy($publicId, $options);
    }

    /**
     * Destroy a Cloudinary resource.
     *
     * @param string $publicId
     * @param array  $options
     * @return array
     */
    public function destroy(string $publicId, $options = []): array
    {
        return $this->getUploader()->destroy($publicId, $options)->getArrayCopy();
    }

    /**
     * Restore a Cloudinary resource (from deletion).
     *
     * @param array $publicIds
     * @param array $options
     * @return null
     */
    public function restore($publicIds = [], $options = [])
    {
        return $this->getApi()->restore($publicIds, $options);
    }

    /**
     * An alias for deleteResources().
     *
     * @param array $publicIds
     * @param array $options
     * @return null
     * @throws ApiError
     */
    public function destroyImages(array $publicIds, $options = [])
    {
        return $this->deleteResources($publicIds, $options);
    }

    /**
     * Delete multiple Cloudinary resources.
     *
     * @param array $publicIds
     * @param array $options
     * @return null
     * @throws ApiError
     */
    public function deleteResources(array $publicIds, $options = [])
    {
        return $this->getApi()->deleteAssets($publicIds, $options);
    }

    /**
     * Delete multiple Cloudinary resources having a prefix.
     *
     * @param string $prefix
     * @param array  $options
     * @return null
     * @throws ApiError
     */
    public function deleteResourcesByPrefix(string $prefix, $options = [])
    {
        return $this->getApi()->deleteAssetsByPrefix($prefix, $options);
    }

    /**
     * Delete all Cloudinary resources.
     *
     * @param array $options
     * @return null
     * @throws ApiError
     */
    public function deleteAllResources($options = [])
    {
        return $this->getApi()->deleteAllAssets($options);
    }

    /**
     * Delete all Cloudinary resources having a specific tag.
     *
     * @param string $tag
     * @param array  $options
     * @return null
     * @throws ApiError
     */
    public function deleteResourcesByTag(string $tag, $options = [])
    {
        return $this->getApi()->deleteAssetsByTag($tag, $options);
    }

    /**
     * Delete multiple Cloudinary resources' transformed images.
     *
     * @param array $publicIds
     * @return null
     * @throws ApiError
     */
    public function deleteDerivedResources($publicIds = [])
    {
        return $this->getApi()->deleteDerivedAssets($publicIds);
    }

    /**
     * An alias of destroy().
     *
     * @param       $publicId
     * @param array $options
     * @return bool
     */
    public function delete($publicId, $options = []): bool
    {
        $response = $this->destroy($publicId, $options);

        return (boolean)($response['result'] === 'ok');
    }

    /**
     * Add a tag to Cloudinary resources.
     *
     * @param string $tag
     * @param array  $publicIds
     * @param array  $options
     * @return mixed
     */
    public function addTag(string $tag, $publicIds = [], $options = [])
    {
        return $this->getUploader()->addTag($tag, $publicIds, $options);
    }

    /**
     * Remove a tag from Cloudinary resources.
     *
     * @param string $tag
     * @param array  $publicIds
     * @param array  $options
     * @return ApiResponse
     */
    public function removeTag(string $tag, $publicIds = [], $options = []): ApiResponse
    {
        return $this->getUploader()->removeTag($tag, $publicIds, $options);
    }

    /**
     * Replace a tag on one or more Cloudinary resources.
     *
     * @param string $tag
     * @param array  $publicIds
     * @param array  $options
     * @return ApiResponse
     */
    public function replaceTag(string $tag, $publicIds = [], $options = []): ApiResponse
    {
        return $this->getUploader()->replaceTag($tag, $publicIds, $options);
    }

    /**
     * Create a zip file containing Cloudinary resources matching specified options.
     *
     * @param array  $options
     * @param null   $nameArchive
     * @param string $mode
     * @return mixed
     */
    public function createArchive($options = [], $nameArchive = null, $mode = 'create')
    {
        $options = array_merge($options, ['target_public_id' => $nameArchive, 'mode' => $mode]);
        return $this->getUploader()->createArchive($options);
    }

    /**
     * Download a zip file containing Cloudinary resources matching options.
     *
     * @param array $options
     * @param null  $nameArchive
     * @return mixed
     */
    public function downloadArchiveUrl($options = [], $nameArchive = null)
    {
        $options = array_merge($options, ['target_public_id' => $nameArchive]);
        return $this->getUploader()->downloadArchiveUrl($options);
    }


    /**
     * Returns a list of Cloudinary resources.
     *
     * @param array $options
     * @return ApiResponse
     */
    public function resources($options = []): ApiResponse
    {
        return $this->getApi()->assets($options);
    }

    /**
     * Returns a list of Cloudinary resources specified by public IDs.
     *
     * @param array $publicIds
     * @param array $options
     * @return ApiResponse
     */
    public function resourcesByIds(array $publicIds, $options = []): ApiResponse
    {
        return $this->getApi()->assetsByIds($publicIds, $options);
    }

    /**
     * Returns a list of Cloudinary resources specified by a tag.
     *
     * @param string $tag
     * @param array  $options
     * @return ApiResponse
     */
    public function resourcesByTag(string $tag, $options = []): ApiResponse
    {
        return $this->getApi()->assetsByTag($tag, $options);
    }

    /**
     * Returns a list of Cloudinary resources specified by moderation status.
     *
     * @param string $kind
     * @param string $status
     * @param array  $options
     * @return ApiResponse
     */
    public function resourcesByModeration(string $kind, string $status, $options = []): ApiResponse
    {
        return $this->getApi()->assetsByModeration($kind, $status, $options);
    }

    /**
     * Returns a list of tags.
     *
     * @param array $options
     * @return ApiResponse
     * @throws ApiError
     */
    public function tags($options = []): ApiResponse
    {
        return $this->getApi()->tags($options);
    }

    /**
     * Display a Cloudinary resource.
     *
     * @param string $publicId
     * @param array  $options
     * @return ApiResponse
     */
    public function resource(string $publicId, $options = []): ApiResponse
    {
        return $this->getApi()->asset($publicId, $options);
    }

    /**
     * Updates a Cloudinary resource.
     *
     * @param string $publicId
     * @param array  $options
     * @return ApiResponse
     */
    public function update(string $publicId, $options = []): ApiResponse
    {
        return $this->getApi()->update($publicId, $options);
    }

    /**
     * Returns a List of available transformations.
     *
     * @param array $options
     * @return ApiResponse
     */
    public function transformations($options = []): ApiResponse
    {
        return $this->getApi()->transformations($options);
    }

    /**
     * Returns information about a transformation.
     *
     * @param string $transformation
     * @param array  $options
     * @return ApiResponse
     */
    public function transformation(string $transformation, $options = []): ApiResponse
    {
        return $this->getApi()->transformation($transformation, $options);
    }

    /**
     * Delete a transformation.
     *
     * @param string $transformation
     * @param array  $options
     * @return ApiResponse
     * @throws ApiError
     */
    public function deleteTransformation(string $transformation, $options = []): ApiResponse
    {
        return $this->getApi()->deleteTransformation($transformation, $options);
    }

    /**
     * Update a transformation.
     *
     * @param string $transformation
     * @param array  $updates
     * @return ApiResponse
     * @throws ApiError
     */
    public function updateTransformation(string $transformation, $updates = []): ApiResponse
    {
        return $this->getApi()->updateTransformation($transformation, $updates);
    }

    /**
     * Create a transformation.
     *
     * @param string $name
     * @param string $definition
     * @return ApiResponse
     */
    public function createTransformation(string $name, string $definition): ApiResponse
    {
        return $this->getApi()->createTransformation($name, $definition);
    }

    /**
     * Returns a list of upload mappings.
     *
     * @param array $options
     * @return ApiResponse
     */
    public function uploadMappings($options = []): ApiResponse
    {
        return $this->getApi()->uploadMappings($options);
    }

    /**
     * Returns information about an upload mapping.
     *
     * @param string $name
     * @return ApiResponse
     */
    public function uploadMapping(string $name): ApiResponse
    {
        return $this->getApi()->uploadMapping($name);
    }

    /**
     * Create an upload mapping.
     *
     * @param string $name
     * @param array  $options
     * @return ApiResponse
     */
    public function createUploadMapping(string $name, $options = []): ApiResponse
    {
        return $this->getApi()->createUploadMapping($name, $options);
    }

    /**
     * Delete an upload mapping.
     *
     * @param string $name
     * @return ApiResponse
     * @throws ApiError
     */
    public function deleteUploadMapping(string $name): ApiResponse
    {
        return $this->getApi()->deleteUploadMapping($name);
    }

    /**
     * Update an upload mapping.
     *
     * @param string $name
     * @param array  $options
     * @return ApiResponse
     * @throws ApiError
     */
    public function updateUploadMapping(string $name, $options = []): ApiResponse
    {
        return $this->getApi()->updateUploadMapping($name, $options);
    }

    /**
     * Returns a list of upload presets.
     *
     * @param array $options
     * @return ApiResponse
     */
    public function uploadPresets($options = []): ApiResponse
    {
        return $this->getApi()->uploadPresets($options);
    }

    /**
     * Returns information about an upload preset.
     *
     * @param string $name
     * @param array  $options
     * @return ApiResponse
     */
    public function uploadPreset(string $name, $options = []): ApiResponse
    {
        return $this->getApi()->uploadPreset($name, $options);
    }

    /**
     * Create an upload preset.
     *
     * @param string $name
     * @return ApiResponse
     */
    public function createUploadPreset(string $name): ApiResponse
    {
        return $this->getApi()->createUploadPreset($name);
    }

    /**
     * Delete an upload preset.
     *
     * @param string $name
     * @return ApiResponse
     * @throws ApiError
     */
    public function deleteUploadPreset(string $name): ApiResponse
    {
        return $this->getApi()->deleteUploadPreset($name);
    }

    /**
     * Update an upload preset.
     *
     * @param string $name
     * @param array  $options
     * @return ApiResponse
     * @throws ApiError
     */
    public function updateUploadPreset(string $name, $options = []): ApiResponse
    {
        return $this->getApi()->updateUploadPreset($name, $options);
    }

    /**
     * Returns a list of root folders.
     *
     * @param array $options
     * @return ApiResponse
     */
    public function rootFolders($options = []): ApiResponse
    {
        return $this->getApi()->rootFolders($options);
    }

    /**
     * Returns a list of subfolders.
     *
     * @param string $name
     * @param array  $options
     * @return ApiResponse
     * @throws ApiError
     */
    public function subfolders(string $name, $options = []): ApiResponse
    {
        return $this->getApi()->subfolders($name, $options);
    }

    /**
     * Returns Cloudinary usage details.
     *
     * @param array $options
     * @return ApiResponse
     * @throws ApiError
     */
    public function usage($options = []): ApiResponse
    {
        return $this->getApi()->usage($options);
    }

    /**
     * Ping Cloudinary servers.
     *
     * @return ApiResponse
     */
    public function ping(): ApiResponse
    {
        return $this->getApi()->ping();
    }
}
