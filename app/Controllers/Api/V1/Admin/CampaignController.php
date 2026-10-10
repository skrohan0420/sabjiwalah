<?php
namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\AdminCampaignService;
use App\Services\CampaignValidationException;
use App\Services\OrderConflictException;

abstract class CampaignController extends BaseApiController
{
    protected string $kind;

    public function index()
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $query = $this->request->getGet();
        foreach ($query as $key => $value) if (!is_string($value)) return $this->validationError([$key => 'Use a single query value.']);
        if (!$this->validateData($query, ['page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[1000000]',
            'per_page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]', 'search' => 'permit_empty|max_length[120]',
            'status' => 'permit_empty|in_list[enabled,disabled,scheduled,expired]'])) return $this->validationError($this->validator->getErrors());
        $query['page'] = (int)($query['page'] ?? 1) ?: 1; $query['per_page'] = (int)($query['per_page'] ?? 20) ?: 20;
        try { return $this->success((new AdminCampaignService($this->kind))->listing($query)); }
        catch (\Throwable) { return $this->error('Campaigns are temporarily unavailable.', 503); }
    }

    public function show(string $uid)
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        try {
            $row = (new AdminCampaignService($this->kind))->details($uid);
            return $row ? $this->success($row) : $this->error('Campaign not found.', 404);
        } catch (\Throwable) { return $this->error('Campaign is temporarily unavailable.', 503); }
    }

    public function create() { return $this->save(null); }
    public function update(string $uid) { return $this->save($uid); }

    private function save(?string $uid)
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        try {
            $row = (new AdminCampaignService($this->kind))->save($this->requestData(), $uid);
            return $this->success($row, 'Campaign saved.', $uid === null ? 201 : 200);
        } catch (CampaignValidationException $e) { return $this->validationError($e->errors);
        } catch (\OutOfBoundsException $e) { return $this->error($e->getMessage(), 404);
        } catch (OrderConflictException $e) { return $this->error($e->getMessage(), 409);
        } catch (\Throwable) { return $this->error('Unable to save campaign. Reload before trying again.', 503); }
    }
}
