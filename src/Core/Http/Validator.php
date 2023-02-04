<?php

namespace App\Core\Http;

use App\Core\Exceptable;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

class Validator extends \Rakit\Validation\Validator
{
    /**
     * Constructor.
     */
    public function __construct ()
    {
        parent::__construct();

        $this->setMessage('present', 'present');
        $this->setMessage('required', 'required');
        $this->setMessage('required_if', 'required');
        $this->setMessage('required_unless', 'required');
        $this->setMessage('required_with', 'required');
        $this->setMessage('required_without', 'required');
        $this->setMessage('required_with_all', 'required');
        $this->setMessage('required_without_all', 'required');

        $this->setMessage('email', 'valid_email');
        $this->setMessage('url', 'valid_url :schemes');
        $this->setMessage('extension', 'valid_url_extension [:allowed_extensions]');
        $this->setMessage('ip', 'valid_ip [\'v4\', \'v6\']');
        $this->setMessage('ipv4', 'valid_ip [\'v4\']');
        $this->setMessage('ipv6', 'valid_ip [\'v6\']');
        $this->setMessage('json', 'valid_json');

        $this->setMessage('uppercase', 'uppercase');
        $this->setMessage('lowercase', 'lowercase');
        $this->setMessage('alpha_num', 'alpha_numeric');
        $this->setMessage('alpha_dash', 'alpha_dash');
        $this->setMessage('alpha_spaces', 'alpha_space');
        $this->setMessage('regex', 'valid_format');

        $this->setMessage('uploaded_file', 'valid_file {"min": ":min_size", "max": ":max_size", "types": :allowed_types}');
        $this->setMessage('mimes', 'valid_file_mime [:allowed_types]');

        $this->setMessage('date', 'valid_date [\':format\']');
        $this->setMessage('after', 'valid_date_after [\':time\']');
        $this->setMessage('before', 'valid_date_before [\':time\']');

        $this->setTranslations([ 'and' => '', 'or' => '', ]);
        $this->setMessage('in', 'one_of [:allowed_values]');
        $this->setMessage('not_in', 'not_one_of [:allowed_values]');

        $this->setMessage('min', 'min [:min]');
        $this->setMessage('max', 'max [:min]');
        $this->setMessage('between', 'between [:min, :max]');

        $this->setMessage('digits', 'digit_length [:digits]');
        $this->setMessage('digits_between', 'digit_length_between [:min, :max]');

        $this->setMessage('integer', 'integer');
        $this->setMessage('boolean', 'boolean');
        $this->setMessage('accepted', 'boolean [true]');
        $this->setMessage('array', 'array');

        $this->setMessage('same', 'same_with [\':field\']');
        $this->setMessage('different', 'different_with [\':field\']');
    }

    /**
     * Get data from request and validate it.
     *
     * @param ServerRequestInterface $request
     * @param array $rules
     * @return object
     * @throws Exceptable
     */
    public function get (ServerRequestInterface $request, array $rules): object
    {
        $data = $this->getData($request);
        $validation = $this->make(array_merge($data, $this->revertUploadedFilesToPhpSpec($request->getUploadedFiles())), $rules);
        $validation->validate();

        if ($validation->fails()) {
            throw new Exceptable('validation_failed', 400, [
                'detail' => $validation->errors()->toArray()
            ]);
        }

        return (object) $data;
    }

    /**
     * Get data from request.
     *
     * @param ServerRequestInterface $request
     * @return array
     */
    private function getData (ServerRequestInterface $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');
        if (str_starts_with($contentType, 'application/json')) {
            return json_decode($request->getBody()->getContents(), true);
        }
        return $request->getParsedBody() ?? [];
    }

    /**
     * Revert uploaded files to PHP spec.
     *
     * @param array<UploadedFileInterface> $uploadedFile
     * @param array $store
     * @return array
     */
    private function revertUploadedFilesToPhpSpec (array $uploadedFile, array &$store = []): array
    {
        foreach ($uploadedFile as $key => $value) {
            if (is_array($value)) {
                $store[$key] = [];
                $this->revertUploadedFilesToPhpSpec($value, $store[$key]);
            }
            else {
                $store[$key] = [
                    'name' => $value->getClientFilename(),
                    'type' => $value->getClientMediaType(),
                    'tmp_name' => $value->getStream()->getMetadata('uri'),
                    'error' => $value->getError(),
                    'size' => $value->getSize()
                ];
            }
        }

        return $store;
    }
}