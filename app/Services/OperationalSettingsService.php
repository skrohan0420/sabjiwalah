<?php
namespace App\Services;
class OperationalSettingsService
{
    private const FIELDS = ['shop_open','orders_paused','delivery_charge','free_delivery_minimum','minimum_order_amount','opens_at','closes_at','maximum_active_orders'];
    public function get(bool $lock = false): array
    {
        $db = db_connect();
        $row = $lock ? $db->query('SELECT * FROM '.$db->protectIdentifiers('operational_settings',true).' WHERE id = 1 FOR UPDATE')->getRowArray()
            : $db->table('operational_settings')->where('id',1)->get()->getRowArray();
        if (!$row) throw new \RuntimeException('Operational settings are unavailable.');
        $values = $this->validate(array_intersect_key($row,array_flip(self::FIELDS)));
        return $values + ['revision'=>(int)$row['revision'],'updated_at'=>(new \DateTimeImmutable($row['updated_at'],new \DateTimeZone(config('App')->appTimezone)))->format(DATE_ATOM)];
    }
    public function history(): array
    {
        $rows = db_connect()->table('operational_setting_history h')->select('h.old_values,h.new_values,h.created_at,u.name AS admin_name')
            ->join('users u','u.id=h.changed_by')->orderBy('h.id','DESC')->limit(10)->get()->getResultArray();
        return array_map(static fn($row)=>['old_values'=>json_decode($row['old_values'],true,512,JSON_THROW_ON_ERROR),
            'new_values'=>json_decode($row['new_values'],true,512,JSON_THROW_ON_ERROR),'admin_name'=>$row['admin_name'],
            'created_at'=>(new \DateTimeImmutable($row['created_at'],new \DateTimeZone(config('App')->appTimezone)))->format(DATE_ATOM)],$rows);
    }
    public function save(array $input, int $adminId): array
    {
        if (array_diff(array_keys($input),array_merge(self::FIELDS,['expected_revision']))) throw new SettingsValidationException(['payload'=>'Unknown settings fields.']);
        $data=$this->validate($input);
        $expected=$input['expected_revision']??null;
        if ((!is_int($expected)&&!is_string($expected))||!preg_match('/^[1-9]\d{0,9}$/D',(string)$expected)||(float)$expected>4294967295)
            throw new SettingsValidationException(['expected_revision'=>'Reload settings before saving.']);
        $db=db_connect(); if (!$db->transBegin()) throw new \RuntimeException('Unable to begin settings update.');
        try {
            $before=$this->get(true);
            $admin=$db->query('SELECT id FROM '.$db->protectIdentifiers('users',true)." WHERE id=? AND role='admin' AND status='active' FOR UPDATE",[$adminId])->getRowArray();
            if (!$admin) throw new \OutOfBoundsException('An active admin account is required.');
            if ($before['revision']!==(int)$expected) throw new OrderConflictException('These settings changed. Refresh before saving.');
            $old=array_intersect_key($before,array_flip(self::FIELDS));
            if ($old!==$data) {
                if ($before['revision'] >= 4294967295) throw new \RuntimeException('Settings revision limit reached.');
                if (!$db->table('operational_settings')->where('id',1)->update($data+['revision'=>$before['revision']+1,'updated_at'=>date('Y-m-d H:i:s')])
                    || !$db->table('operational_setting_history')->insert(['changed_by'=>$adminId,'old_values'=>json_encode($old,JSON_THROW_ON_ERROR),
                        'new_values'=>json_encode($data,JSON_THROW_ON_ERROR),'created_at'=>date('Y-m-d H:i:s')])) throw new \RuntimeException('Unable to save settings and history.');
            }
            $result=$this->get(true);
            if (!$db->transStatus()||!$db->transCommit()) throw new \RuntimeException('Unable to save settings.');
            return $result;
        } catch (\Throwable $e) { $db->transRollback(); throw $e; }
    }
    private function validate(array $input): array
    {
        $errors=[];$data=[];
        foreach(self::FIELDS as $key) {
            $value=$input[$key]??null;
            if ($value!==null&&!is_string($value)&&!is_int($value)&&!is_float($value)&&!is_bool($value)) { $errors[$key]='Use a single field value.';continue; }
            if (in_array($key,['shop_open','orders_paused'],true)) {
                if (!in_array($value,[0,1,'0','1',true,false],true)) $errors[$key]='Choose yes or no.';
                $data[$key]=(int)$value;continue;
            }
            if (is_bool($value)) { $errors[$key]='Use a text or number field.';continue; }
            $value=$value===null?null:trim((string)$value);if($value==='')$value=null;
            if (in_array($key,['delivery_charge','minimum_order_amount','free_delivery_minimum'],true)) {
                if ($value===null&&$key==='free_delivery_minimum'){$data[$key]=null;continue;}
                if ($value===null||!preg_match('/^\d{1,6}(?:\.\d{1,2})?$/D',$value))$errors[$key]='Use an amount from 0 to 999999.99 with at most two decimal places.';
                $data[$key]=number_format((float)$value,2,'.','');
            } elseif ($key==='maximum_active_orders') {
                if ($value!==null&&(!preg_match('/^[1-9]\d{0,5}$/D',$value)||(int)$value>100000))$errors[$key]='Use a whole number from 1 to 100000, or leave blank for unlimited.';
                $data[$key]=$value===null?null:(int)$value;
            } else {
                if ($value!==null&&!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$value))$errors[$key]='Use a valid time in HH:MM format.';
                $data[$key]=$value;
            }
        }
        if ((($data['opens_at']??null)===null)!==(($data['closes_at']??null)===null)) $errors['opens_at']='Provide both opening and closing times, or leave both blank.';
        if (!empty($data['opens_at'])&&$data['opens_at']===($data['closes_at']??null))$errors['closes_at']='Opening and closing times must differ. Leave both blank for all-day ordering.';
        if($errors)throw new SettingsValidationException($errors);return$data;
    }
    public function notice(array $settings, float $subtotal, bool $lock = false, ?\DateTimeImmutable $now = null): ?string
    {
        if (!$settings['shop_open']) return 'The shop is closed for new orders.';
        if ($settings['orders_paused']) return 'New orders are temporarily paused.';
        $time=($now??new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('Asia/Kolkata'))->format('H:i');
        $open=$settings['opens_at'];$close=$settings['closes_at'];
        if ($open!==null&&($open<$close?($time<$open||$time>=$close):($time<$open&&$time>=$close)))
            return 'Ordering is available from '.$open.' to '.$close.' India time.';
        if ($subtotal<(float)$settings['minimum_order_amount']) return 'The minimum cart subtotal is ₹'.$settings['minimum_order_amount'].'.';
        if ($settings['maximum_active_orders']!==null) {
            $db=db_connect();$query=$db->table('orders')->select('COUNT(*) AS active_count',false)
                ->whereIn('order_status',['pending','confirmed','preparing','ready_for_delivery','out_for_delivery'])->getCompiledSelect();
            $count=(int)$db->query($query.($lock?' FOR UPDATE':''))->getRowArray()['active_count'];
            if($count>=$settings['maximum_active_orders'])return 'Order capacity is full. Please try again later.';
        }
        return null;
    }
}
