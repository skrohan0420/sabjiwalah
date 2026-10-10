<?php
namespace App\Services;

use App\Models\OfferModel;
use App\Models\PromotionModel;

class AdminCampaignService
{
    private const COMMON = ['starts_at', 'ends_at', 'is_active'];
    private const OFFER = ['name', 'code', 'type', 'value', 'minimum_order_amount', 'maximum_discount', 'usage_limit'];
    private const PROMOTION = ['title', 'image', 'link', 'position'];

    public function __construct(private string $kind)
    {
        if (!in_array($kind, ['offers', 'promotions'], true)) throw new \InvalidArgumentException('Unknown campaign type.');
    }

    private function model(): OfferModel|PromotionModel
    {
        return $this->kind === 'offers' ? new OfferModel() : new PromotionModel();
    }

    private function fields(): array
    {
        return array_merge(self::COMMON, $this->kind === 'offers' ? self::OFFER : self::PROMOTION);
    }

    public function listing(array $filters): array
    {
        $now = date('Y-m-d H:i:s');
        $builder = db_connect()->table($this->kind);
        $label = $this->kind === 'offers' ? 'name' : 'title';
        if (($filters['search'] ?? '') !== '') {
            $builder->groupStart()->like($label, $filters['search']);
            if ($this->kind === 'offers') $builder->orLike('code', $filters['search']);
            $builder->groupEnd();
        }
        switch ($filters['status'] ?? '') {
            case 'disabled': $builder->where('is_active', 0); break;
            case 'expired': $builder->where('is_active', 1)->where('ends_at <=', $now); break;
            case 'scheduled':
                $builder->where('is_active', 1)->where('starts_at >', $now)
                    ->groupStart()->where('ends_at', null)->orWhere('ends_at >', $now)->groupEnd(); break;
            case 'enabled':
                $builder->where('is_active', 1)->groupStart()->where('starts_at', null)->orWhere('starts_at <=', $now)->groupEnd()
                    ->groupStart()->where('ends_at', null)->orWhere('ends_at >', $now)->groupEnd(); break;
        }
        $total = $builder->countAllResults(false);
        $page = $filters['page']; $perPage = $filters['per_page'];
        $rows = $builder->orderBy('id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        return ['items' => array_map(fn($row) => $this->publicRow($row), $rows),
            'pager' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'page_count' => (int)ceil($total / $perPage)]];
    }

    public function details(string $uid): ?array
    {
        $row = $this->model()->where('uid', $uid)->first();
        return $row ? $this->publicRow($row) : null;
    }

    public function save(array $input, ?string $uid = null): array
    {
        $allowed = $this->fields();
        if ($uid !== null) $allowed[] = 'expected_revision';
        if (array_diff(array_keys($input), $allowed)) throw new CampaignValidationException(['payload' => 'Unknown campaign fields.']);
        foreach ($input as $key => $value) {
            if (($value !== null && !is_string($value) && !is_int($value) && !is_bool($value)) || (is_bool($value) && $key !== 'is_active')) {
                throw new CampaignValidationException([$key => 'Use a text or whole-number field.']);
            }
        }
        $data = $this->validate($input);
        if ($uid !== null && (!isset($input['expected_revision']) || !is_string($input['expected_revision'])
            || !preg_match('/^[a-f0-9]{64}$/D', $input['expected_revision']))) {
            throw new CampaignValidationException(['expected_revision' => 'Reload this campaign before editing.']);
        }
        $db = db_connect();
        if (!$db->transBegin()) throw new \RuntimeException('Unable to begin campaign update.');
        try {
            $model = $this->model();
            if ($uid !== null) {
                $row = $db->query('SELECT * FROM ' . $db->protectIdentifiers($this->kind, true) . ' WHERE uid = ? FOR UPDATE', [$uid])->getRowArray();
                if (!$row) throw new \OutOfBoundsException('Campaign not found.');
                if (!hash_equals($this->revision($row), $input['expected_revision'])) throw new OrderConflictException('This campaign changed. Reload before saving.');
                $saved = $model->skipValidation(true)->update($row['id'], $data);
                $id = (int)$row['id'];
            } else {
                $id = $model->skipValidation(true)->insert($data);
                $saved = (bool)$id;
            }
            if (!$saved || !$db->transStatus()) {
                if ((int)($db->error()['code'] ?? 0) === 1062) throw new CampaignValidationException(['code' => 'This offer code already exists.']);
                throw new \RuntimeException('Unable to save campaign.');
            }
            $result = $this->publicRow($model->find($id));
            if (!$db->transCommit()) throw new \RuntimeException('Unable to save campaign.');
            return $result;
        } catch (\Throwable $e) {
            $duplicate = (int)($db->error()['code'] ?? 0) === 1062;
            $db->transRollback();
            if ($duplicate) throw new CampaignValidationException(['code' => 'This offer code already exists.']);
            throw $e;
        }
    }

    private function validate(array $input): array
    {
        $errors = []; $data = [];
        $text = function(string $field, int $max, bool $required = false) use ($input, &$errors): ?string {
            $value = trim((string)($input[$field] ?? ''));
            if (($required && $value === '') || mb_strlen($value) > $max) $errors[$field] = 'Provide ' . ($required ? '1–' : 'up to ') . $max . ' characters.';
            return $value === '' ? null : $value;
        };
        if (!in_array($input['is_active'] ?? null, [0, 1, '0', '1', false, true], true)) $errors['is_active'] = 'Choose enabled or disabled.';
        $data['is_active'] = (int)($input['is_active'] ?? 0);
        foreach (['starts_at', 'ends_at'] as $field) {
            $value = $text($field, 40);
            $data[$field] = null;
            if ($value === null) continue;
            // Accept explicit offsets only; browser datetime-local values are converted to ISO before submission.
            if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $value)) {
                $errors[$field] = 'Provide a valid ISO date with a timezone.'; continue;
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', str_replace('Z', '+00:00', $value));
            $warnings = \DateTimeImmutable::getLastErrors();
            if (!$date || ($warnings !== false && ($warnings['warning_count'] || $warnings['error_count']))
                || (int)$date->format('Y') < 1000) { $errors[$field] = 'Provide a valid date.'; continue; }
            $date = $date->setTimezone(new \DateTimeZone(config('App')->appTimezone));
            if ((int)$date->format('Y') < 1000 || (int)$date->format('Y') > 9999) { $errors[$field] = 'Date is outside the supported range.'; continue; }
            $data[$field] = $date->format('Y-m-d H:i:s');
        }
        if ($data['starts_at'] && $data['ends_at'] && $data['ends_at'] <= $data['starts_at']) $errors['ends_at'] = 'End must be later than start.';
        if ($this->kind === 'offers') {
            $data['name'] = $text('name', 150, true);
            $data['code'] = strtoupper($text('code', 80, true) ?? '');
            if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{0,79}$/D', $data['code'])) $errors['code'] = 'Use letters, numbers, underscores or hyphens.';
            $data['type'] = $text('type', 20, true);
            if (!in_array($data['type'], ['fixed', 'percentage'], true)) $errors['type'] = 'Choose fixed or percentage.';
            foreach (['value', 'minimum_order_amount', 'maximum_discount'] as $field) {
                $value = $text($field, 11, $field === 'value');
                $data[$field] = $value;
                if ($value !== null && (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $value)
                    || ($field !== 'minimum_order_amount' && (float)$value <= 0))) $errors[$field] = 'Use a positive amount with at most two decimal places.';
            }
            if ($data['type'] === 'percentage' && (float)$data['value'] > 100) $errors['value'] = 'Percentage cannot exceed 100.';
            $limit = $text('usage_limit', 10);
            if ($limit !== null && (!preg_match('/^[1-9]\d{0,9}$/D', $limit) || (float)$limit > 4294967295)) $errors['usage_limit'] = 'Use a whole number from 1 to 4294967295.';
            $data['usage_limit'] = $limit;
        } else {
            $data['title'] = $text('title', 150, true);
            $data['position'] = $text('position', 80);
            foreach (['image', 'link'] as $field) {
                $data[$field] = $text($field, 255);
                if ($data[$field] !== null && !$this->safeLocation($data[$field])) $errors[$field] = 'Use an HTTPS URL without credentials or a site path starting with /.';
            }
        }
        if ($errors) throw new CampaignValidationException($errors);
        return $data;
    }

    private function safeLocation(string $value): bool
    {
        return (new CampaignLocationService())->safe($value);
    }

    private function revision(array $row): string
    {
        return hash('sha256', json_encode(array_intersect_key($row, array_flip(array_merge(['uid', 'updated_at'], $this->fields()))), JSON_THROW_ON_ERROR));
    }

    private function publicRow(array $row): array
    {
        $result = array_intersect_key($row, array_flip(array_merge(['uid'], $this->fields())));
        $result['revision'] = $this->revision($row);
        $result['is_active'] = (bool)$row['is_active'];
        $now = date('Y-m-d H:i:s');
        $result['status'] = !(bool)$row['is_active'] ? 'disabled' : ($row['ends_at'] && $row['ends_at'] <= $now ? 'expired'
            : ($row['starts_at'] && $row['starts_at'] > $now ? 'scheduled' : 'enabled'));
        foreach (['starts_at', 'ends_at'] as $field) $result[$field] = $row[$field]
            ? (new \DateTimeImmutable($row[$field], new \DateTimeZone(config('App')->appTimezone)))->format(DATE_ATOM) : null;
        return $result;
    }
}
