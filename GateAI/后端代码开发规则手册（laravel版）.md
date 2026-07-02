# 后端接口开发手册

**在使用ai帮助开发后必须检查代码是否符合接口开发手册对应的前后端开发规则！**，后端接口开发手册部分内容比较偏代码底层，大家开发时不止需要按照这个手册开发，还需要理解为什么需要这么开发

## 一、 后端接口文档规范

### 1. 基本要求

后端开发在与前端对接过程中，**所有接口必须提供接口文档**，并严格遵守以下规则：

-  每个业务模块必须对应一份接口文档 
-  接口新增、修改、删除后，必须同步更新接口文档 
-  文档更新后必须及时通知前端 
-  接口文档必须保持统一格式，禁止随意编写 

### 2.接口文档字段规范

所有接口必须包含以下标准字段：

-  接口路径 
-  请求方式 
-  请求参数 
-  请求参数类型 
-  是否必须传入 
-  返回参数 
-  返回参数类型 
-  备注 

------

### 三、接口文档归类规范

接口文档统一存放路径：

```
D:\ruidao\laravel-first\NewRuiDao\docs\conclusion
```

接口文档分为两类：

------

## 3.1 总结接口文档（模块级全量文档）

**定义**：

用于记录**某个模块的全部接口信息**，属于系统级归档文档。

**内容要求：**

必须包含该模块所有接口，并统一字段结构：

-  接口路径 
-  请求方式 
-  请求参数 
-  请求参数类型 
-  是否必须传入 
-  返回参数 
-  返回参数类型 
-  备注 

**作用：**

-  模块级接口统一管理 
-  便于系统整体维护 
-  作为长期归档资料 

------

## 3.2 前端对接接口文档（交付文档）

用于前后端联调与版本同步，分为两个子类型：

------

#### 3.2.1 接口总对接文档（全量）

**定义：**

用于前端完整对接某模块所有接口。

**内容要求：**

必须包含该模块**所有接口完整信息**：

-  接口路径 
-  请求方式 
-  请求参数 
-  请求参数类型 
-  是否必须传入 
-  返回参数 
-  返回参数类型 

**特点：**

-  强调“完整性” 
-  用于首次对接或整体联调 
-  不包含变更说明 

------

#### 3.2.2 接口修改文档（增量）

**定义：**

用于记录接口变更内容，仅用于同步更新。

**内容要求：**

必须包含：

-  接口路径 
-  请求方式 
-  请求参数 
-  请求参数类型 
-  是否必须传入 
-  返回参数 
-  返回参数类型 
-  备注（必须说明变更内容） 

**记录范围：**

-  新增字段 
-  删除字段 
-  修改字段类型 
-  返回结构变化 
-  业务逻辑调整 

**作用：**

-  前端快速定位变更内容 
-  降低阅读成本 
-  支持版本迭代管理 

## 二、开发框架分层

**核心原则**

```
Request：
只做参数验证

Controller：
只做请求接收与结果返回

Service：
只做业务逻辑

Model：
只做数据访问

事务统一在Service

日志统一在Service

缓存统一在Service

Controller禁止直接操作数据库

Model禁止编写业务逻辑
```

### 

系统采用 Laravel 分层架构开发，统一划分为：

```
Request    ↓Controller    ↓Service    ↓Model    ↓Database
```

------

### 1. Request层（参数验证层）

**职责**

负责：

-  参数合法性验证 
-  参数格式转换 
-  参数默认值处理 
-  权限预检查（可选） 

**禁止**

禁止在 Request 中：

```
User::query();DB::table();Log::info();Redis::get();
```

禁止：

```
public function rules(){    User::where(...); // 禁止数据库业务查询}
```

**正确示例**

```
class CustomerDetailRequest extends FormRequest{    public function rules(): array    {        return [            'id' => [                'required',                'integer',                'min:1',                Rule::exists('customers', 'id')            ]        ];    }    public function messages(): array    {        return [            'id.required' => '客户ID不能为空',            'id.exists' => '客户不存在'        ];    }}
```

------

### 2. Controller层（接口控制层）

**职责**

负责：

-  接收请求 
-  调用 Service 
-  返回统一结果 

**禁止**：

1.

```
public function detail(Request $request){    $customer = Customer::find($request->id);    return response()->json($customer);}
```

2.

```
DB::transaction(function () {});
```

3.

```
Redis::set();
```

4.

```
复杂业务逻辑
```

**Controller原则**

Controller代码原则：

```
控制器代码尽量控制在50行以内
```

Controller只负责：

```
接收↓调用↓返回
```

**正确示例**

```
class CustomerController extends Controller{    public function detail(        CustomerDetailRequest $request    ): JsonResponse {        $data = $this->customerService->detail(            $request->validated('id')        );        return Result::success(            '获取成功',            $data        );    }}
```

------

### 3. Service层（业务逻辑层）

**职责**

所有业务逻辑必须写在 Service。

包括：

```
业务计算业务校验事务控制状态流转日志记录缓存处理调用第三方接口
```

**Service是整个系统核心**

例如：

```
public function createOrder(array $data): Order{    return DB::transaction(function () use ($data) {        $customer = Customer::query()            ->lockForUpdate()            ->findOrFail($data['customer_id']);        if ($customer->balance < $data['amount']) {            throw new BusinessException(                ResponseCode::BALANCE_NOT_ENOUGH            );        }        $order = Order::create($data);        $customer->decrement(            'balance',            $data['amount']        );        Log::channel('business')->info(            '订单创建成功',            [                'order_id' => $order->id            ]        );        return $order;    });}
```

------

### 4. Model层（数据访问层）



负责：

```
数据库表映射关联关系查询作用域数据转换
```

### 

禁止：

```
class Customer extends Model{    public function createOrder()    {        // 业务逻辑    }}
```

禁止：

```
public function calculateCommission(){}
```

禁止：

```
public function sendSms(){}
```

**正确示例**

```
class Customer extends Model{    protected $table = 'customers';    protected $casts = [        'id' => 'integer',        'status' => 'integer',        'created_at' => 'datetime'    ];    public function orders()    {        return $this->hasMany(            Order::class,            'customer_id'        );    }    public function scopeEnabled($query)    {        return $query->where(            'status',            1        );    }}
```

------

### 5. 分层调用规范

**允许调用**

```
Controller    ↓Service    ↓Model
```

**禁止跨层调用**

禁止：

```
Controller    ↓Model
```

例如：

```
public function detail(){    Customer::find(1);}
```

禁止。

------

禁止：

```
Model    ↓Service
```

例如：

```
class Customer extends Model{    public function test()    {        app(CustomerService::class)            ->detail(1);    }}
```

禁止。

------

禁止：

```
Service A    ↓Controller B
```

例如：

```
app(CustomerController::class)    ->detail();
```



## 三、日志核心设计（先理解结构-（封装）

Laravel 底层用的是 Monolog，一般企业会这样分 channel：

```
// config/logging.php

'channels' => [

    /**
     * =========================
     * 业务日志（business）
     * =========================
     * 用途：
     * - 记录核心业务流程
     * - 例如：订单创建、审批流转、合同变更、支付成功等
     *
     * 特点：
     * - 记录正常业务行为（非错误）
     * - 用于业务追踪与回溯
     */
    'business' => [
        // 按天生成日志文件（推荐企业使用 daily，避免单文件过大）
        'driver' => 'daily',

        // 日志存储路径
        'path' => storage_path('logs/business.log'),

        // 记录等级：info 及以上（info / warning / error / critical）
        'level' => 'info',

        // 日志保留天数：14天（平衡存储成本与业务回溯）
        'days' => 14,
    ],


    /**
     * =========================
     * 异常日志（exception）
     * =========================
     * 用途：
     * - 记录系统异常、错误、崩溃
     * - 例如：SQL异常、空指针、接口调用失败、系统错误
     *
     * 特点：
     * - 用于问题排查与线上事故复盘
     */
    'exception' => [
        // 按天生成日志文件
        'driver' => 'daily',

        // 异常日志文件路径
        'path' => storage_path('logs/exception.log'),

        // 只记录 error 及以上级别（error / critical / emergency）
        'level' => 'error',

        // 保留 30 天（用于历史问题追溯）
        'days' => 30,
    ],


    /**
     * =========================
     * 接口日志（api）
     * =========================
     * 用途：
     * - 记录所有 HTTP API 请求
     * - 例如：请求参数、响应结果、接口耗时、状态码
     *
     * 特点：
     * - 用于性能分析、接口监控、流量分析
     */
    'api' => [
        // 按天拆分日志，避免单文件过大
        'driver' => 'daily',

        // API 请求日志文件路径
        'path' => storage_path('logs/api.log'),

        // 记录 info 及以上（请求行为属于正常信息）
        'level' => 'info',

        // 保留 7 天（API日志量大，只保留短期）
        'days' => 7,
    ],
],
```

------

### 1. 日志使用场景

#### 业务关键流程日志（最常用）

**场景**

-  下单 
-  审批流程 
-  合同创建 
-  支付状态变更 

**示例：创建订单**

```
use Illuminate\Support\Facades\Log;public function createOrder(array $data){    Log::channel('business')->info('开始创建订单', [        'user_id' => auth()->id(),        'request_data' => $data,    ]);    $order = Order::create($data);    Log::channel('business')->info('订单创建成功', [        'order_id' => $order->id,        'amount' => $order->amount,    ]);    return $order;}
```

👉 企业意义：

-  可追踪“某个订单是怎么生成的” 
-  出问题可以回溯完整链路 

------

#### 异常日志（必须强制记录）

**场景**

-  SQL异常 
-  空指针 
-  第三方接口失败 
-  业务校验失败 

**示例**

```
try {    $result = $this->payService->pay($orderId);} catch (\Throwable $e) {    Log::channel('exception')->error('支付失败异常', [        'order_id' => $orderId,        'message' => $e->getMessage(),        'file' => $e->getFile(),        'line' => $e->getLine(),        'trace' => $e->getTraceAsString(),    ]);    throw $e;}
```

👉 企业意义：

-  运维可直接定位问题 
-  可接入告警系统（钉钉/飞书/Prometheus） 

------

#### API 请求日志（接口级监控）

**场景**

-  监控所有接口请求 
-  排查慢接口 
-  记录请求参数 

**Middleware 示例**

```
public function handle($request, Closure $next){    $start = microtime(true);    $response = $next($request);    $duration = microtime(true) - $start;    Log::channel('api')->info('API请求记录', [        'url' => $request->fullUrl(),        'method' => $request->method(),        'ip' => $request->ip(),        'user_id' => auth()->id(),        'request' => $request->all(),        'response_status' => $response->status(),        'duration_ms' => round($duration * 1000, 2),    ]);    return $response;}
```

👉 企业意义：

-  可以定位“哪个接口慢” 
-  可做性能优化依据 

------

#### 操作审计日志（谁改了什么）

**场景**

-  修改合同 
-  删除数据 
-  修改权限 

**示例**

```
Log::channel('business')->warning('用户修改合同', [    'user_id' => auth()->id(),    'contract_id' => $contract->id,    'before' => $oldData,    'after' => $newData,]);
```

意义：

-  满足审计要求（金融/政务系统必须） 
-  防止“谁改的找不到” 

------

#### 第三方接口日志（非常重要）

**场景**

-  支付宝/微信支付 
-  外部API调用 
-  微服务调用 

**示例**

```
Log::channel('api')->info('调用支付接口', [    'url' => $url,    'request' => $params,]);$response = Http::post($url, $params);Log::channel('api')->info('支付接口返回', [    'response' => $response->json(),    'http_status' => $response->status(),]);
```

意义：

-  出问题可以对账 
-  判断是“自己系统问题还是第三方问题” 

------

#### 性能日志（慢查询 / 慢接口）

**示例：**

```
$start = microtime(true);$data = User::query()->where('status', 1)->get();$time = microtime(true) - $start;if ($time > 1) {    Log::channel('business')->warning('慢查询警告', [        'sql_time' => $time,        'sql' => 'User::where(status=1)',    ]);}
```

意义：

-  自动发现性能瓶颈 

------

### 2. 企业级日志最佳实践（重点）

####  1. 必须带 trace_id（链路追踪）

```
Log::withContext([    'trace_id' => (string) Str::uuid(),]);
```

------

#### 2. 禁止直接 Log::info 无结构

错误：

```
Log::info("用户登录成功123");
```

正确：

```
Log::info("用户登录成功", [    'user_id' => $user->id,]);
```

------

#### 3. 日志必须分级

| 类型     | level   |
| -------- | ------- |
| 正常业务 | info    |
| 重要流程 | info    |
| 异常     | error   |
| 警告     | warning |

------

### 3. 不要记录敏感信息

 password / token / 身份证

## 四、性能规范（Performance Best Practices）

### 1 分页查询规范（Pagination）

**禁止（全量查询）**

```
User::all();
User::get();
```

> 问题：一次性加载全部数据，占用内存，容易 OOM，严重影响性能。

------

 **正确（分页查询）**

```
User::paginate(20);
```

**推荐优化（API分页）**

```
User::query()    ->select(['id', 'name', 'created_at'])    ->paginate(20);
```

**高级建议（大数据优化）**

```
User::query()    ->select(['id', 'name'])    ->simplePaginate(20);
```

> 适用于：百万级数据列表 API

------

### 2 查询字段最小化（Field Optimization）

 **禁止（全字段查询）**

```
User::select('*')->get();
```

**正确（按需取字段）**

```
User::select('id', 'name')->get();
```

### 原则

-  API接口禁止 `*` 
-  只返回前端必要字段 
-  大字段（text/json）默认不查 

------

### 3 批量插入规范（Bulk Insert）

**禁止（循环插入）**

```
foreach ($data as $item) {    User::create($item);}
```

> 问题：产生 N 次 SQL，严重拖慢性能

------

**确（批量插入）**

```
User::insert($data);
```

------

**优化建议（事务包裹）**

```
DB::transaction(function () use ($data) {    User::insert($data);});
```

------

**批量更新规范（Bulk Update）**

推荐：upsert（标准）

```
User::upsert(    $data,    ['id'],        // 唯一键    ['name', 'email'] // 更新字段);
```

------

**适用场景**

-  同步第三方数据 
-  Excel导入更新 
-  批量状态更新 
-  数据修正任务 

------

### 4.Redis缓存规范（Cache Strategy）

**热点数据必须缓存**

```
Cache::remember($key, 3600, function () {    return User::select('id', 'name')->get();});
```

------

**推荐缓存场景**

✔ 字典数据（dictionary）
 ✔ 配置数据（config）
 ✔ 首页统计数据
 ✔ 权限数据（roles/permissions）
 ✔ 高频查询接口

------

**缓存规范原则**

-  TTL必须明确（禁止永久缓存无策略数据） 
-  key必须标准化（避免污染） 
-  数据变更必须同步清理缓存 

```
Cache::forget($key);
```

------

### 6 队列化处理规范（Queue System）

**禁止（同步执行耗时任务）**

```
Mail::to($user)->send(new OrderMail());
Http::post($url, $data);
```

------

**正确（使用队列）**

```
dispatch(new SendMailJob($user));
```

------

**适用队列任务**

-  发送短信 
-  发送邮件 
-  导出 Excel 
-  生成 PDF 
-  调用第三方接口 
-  图片处理 
-  数据同步 

------

**企业级原则**

> 所有超过 **200ms 的逻辑必须异步化**

------

### 7 N+1 查询防止（重要）

 **禁止**

```
$users = User::all();foreach ($users as $user) {    echo $user->profile->name;}
```

------

**正确（预加载）**

```
User::with('profile')->get();
```

------

### 8 大数据处理规范

 **禁止一次性加载**

```
User::all();
```

------

 **正确（chunk处理）**

```
User::chunk(1000, function ($users) {    foreach ($users as $user) {        // 处理逻辑    }});
```

------

**或（cursor流式处理）**

```
foreach (User::cursor() as $user) {    // 逐条处理}
```

------

### 9 SQL性能规范

**必须禁止**

-  未加索引的 where 查询 
-  LIKE 前置 `%abc` 
-  无限制排序 `orderBy` 大数据表 
-  多表无索引 join 

------

**推荐**

```
User::where('status', 1)    ->where('id', '>', 100)    ->orderBy('id')    ->limit(20)    ->get();
```

------

### 10 总体性能原则（企业级总结）

**核心原则：**

-  不查多余数据 
-  不做重复 SQL 
-  不同步执行耗时任务 
-  不全表扫描 
-  不无分页查询 
-  不滥用 ORM 

------

**推荐技术组合：**

-  ORM（基础查询） 
-  Query Builder（复杂查询） 
-  Raw SQL（高性能场景） 
-  Redis（缓存层） 
-  Queue（异步层）

## 五、事务规范（Transaction）

### 1. 使用原则

事务用于保证数据一致性。

凡是涉及多个数据操作，且业务要求全部成功或全部失败的场景，必须使用事务。

#### 必须使用事务的场景

**1、多个表同时修改**

```
DB::transaction(function () {    Order::create($orderData);    Payment::create($paymentData);    Finance::create($financeData);});
```

------

2、**同一表多条记录修改**

```
DB::transaction(function () {    User::where('id', $id)        ->decrement('balance', 100);    UserLog::create([        'user_id' => $id,        'amount' => 100,    ]);});
```

------

**3、先查询再更新**

防止并发导致数据异常。

```
DB::transaction(function () use ($id) {    $order = Order::lockForUpdate()->findOrFail($id);    if ($order->status !== Order::STATUS_PENDING) {        throw new BusinessException('订单状态异常');    }    $order->update([        'status' => Order::STATUS_PAID    ]);});
```

------

#### 典型业务场景

**订单创建**

```
订单主表订单明细表订单日志表
```

全部成功才允许提交。

------

**费用新增**

```
费用表客户余额表费用流水表
```

必须保持一致。

------

**财务销账**

```
应收账款表销账记录表客户余额表
```

必须事务。

------

**合同生成**

```
合同主表合同附件表合同审批表
```

必须事务。

------

**提成计算**

```
业绩表提成表财务流水表
```

必须事务。

------

### 2 禁止滥用事务

事务会锁定资源。

事务范围越大：

```
锁表时间越长死锁概率越高数据库压力越大
```

因此：

**事务中禁止执行**:

**HTTP接口调用**

错误示例：

```
DB::transaction(function () {    Order::create();    Http::post($url);});
```

原因：

```
第三方接口响应慢导致数据库长时间持锁
```

------

**文件上传**

错误示例：

```
DB::transaction(function () {    Contract::create();    Storage::putFile();});
```

------

**邮件发送**

错误示例：

```
DB::transaction(function () {    User::create();    Mail::send();});
```

------

**MQ消息发送**

错误示例：

```
DB::transaction(function () {    Order::create();    RabbitMQ::publish();});
```

应使用：

```
DB::afterCommit(function () {    dispatch(new SendOrderMessageJob());});
```

------

### 3 事务嵌套规范

禁止业务层重复开启事务。

错误示例：

```
ServiceA └── transaction()      ServiceB      └── transaction()
```

容易造成代码混乱。

------

推荐：

```
Controller└── Service    └── transaction()
```

或者

```
ApplicationService└── transaction()    ├── OrderService    ├── FinanceService    └── LogService
```

统一管理事务边界。

------

### 4 异常处理规范

事务内发生异常必须回滚。

推荐写法：

```
try {    DB::transaction(function () {        Order::create();        Payment::create();        Finance::create();    });} catch (\Throwable $e) {    Log::error('订单创建失败', [        'message' => $e->getMessage(),        'trace_id' => request()->attributes->get('trace_id'),    ]);    throw $e;}
```

------

### 5 并发控制规范

涉及金额、库存、余额、状态流转时，必须考虑并发问题。

推荐：

**悲观锁**

```
$order = Order::lockForUpdate()    ->findOrFail($id);
```

适用于：

```
库存扣减余额扣减订单支付销账处理
```

------

**乐观锁**

表增加：

```
version int default 1
```

更新：

```
Order::where([    'id' => $id,    'version' => $version])->update([    'version' => DB::raw('version + 1')]);
```

适用于：

```
低冲突场景高并发更新场景
```

## 六、异常处理与报错规范规范：-（封装）

### 1. 异常处理实际使用

#### 1.1 ResponseCode 枚举

```
enum ResponseCode: int
{
    /**
     * 成功
     */
    case SUCCESS = 0;

    /**
     * 参数异常
     */
    case PARAM_ERROR = 10001;

    /**
     * 未登录
     */
    case UNAUTHORIZED = 20001;

    /**
     * 无权限
     */
    case FORBIDDEN = 20002;

    /**
     * 数据不存在
     */
    case DATA_NOT_FOUND = 30001;

    /**
     * 业务异常
     */
    case BUSINESS_ERROR = 40001;

    /**
     * 第三方接口异常
     */
    case THIRD_PARTY_ERROR = 50001;

    /**
     * 数据库异常
     */
    case DATABASE_ERROR = 60001;

    /**
     * 系统异常
     */
    case SYSTEM_ERROR = 90001;

    public function msg(): string
    {
        return match ($this) {

            self::SUCCESS => '成功',

            self::PARAM_ERROR => '参数错误',

            self::UNAUTHORIZED => '未登录',

            self::FORBIDDEN => '无权限访问',

            self::DATA_NOT_FOUND => '记录不存在',

            self::BUSINESS_ERROR => '业务处理失败',

            self::THIRD_PARTY_ERROR => '第三方服务异常',

            self::DATABASE_ERROR => '数据库异常',

            self::SYSTEM_ERROR => '系统异常',
        };
    }
}
```

------

#### 1.2 Result 统一响应类

文件：

```
app/Support/Result.php
```

代码：

```
<?php

namespace App\Support;

use App\Enums\ResponseCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;

/**
 * 统一响应类
 *
 * 所有接口必须通过 Result 返回数据
 */
class Result
{
    /**
     * 成功响应
     *
     * @param string $msg 提示信息
     * @param mixed $data 返回数据
     */
    public static function success(
        string $msg = '成功',
        mixed $data = null
    ): JsonResponse {
        return response()->json([
            'code' => ResponseCode::SUCCESS->value,
            'msg' => $msg,
            'data' => $data,
            'success' => true,

            // TraceId链路追踪ID
            'trace_id' => request()->attributes->get('trace_id'),
        ]);
    }

    /**
     * 失败响应
     *
     * @param ResponseCode $code 错误码枚举
     * @param string|null $msg 自定义错误消息
     * @param mixed $data 返回数据
     */
    public static function error(
        ResponseCode $code,
        ?string $msg = null,
        mixed $data = null
    ): JsonResponse {
        return response()->json([
            'code' => $code->value,
            'msg' => $msg ?? $code->msg(),
            'data' => $data,
            'success' => false,

            // TraceId链路追踪ID
            'trace_id' => request()->attributes->get('trace_id'),
        ]);
    }
}
```

------

#### 1.3 TraceIdMiddleware

文件：

```
app/Http/Middleware/TraceIdMiddleware.php
```

代码：

```
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TraceIdMiddleware
{
    /**
     * 为每个请求生成唯一TraceId
     */
    public function handle($request, Closure $next)
    {
        $traceId = (string) Str::uuid();

        /**
         * 存入当前Request对象
         */
        $request->attributes->set(
            'trace_id',
            $traceId
        );

        /**
         * 写入日志上下文
         */
        Log::withContext([
            'trace_id' => $traceId,
        ]);

        return $next($request);
    }
}
```

------

#### 1.4  Controller写法

Controller 不关心错误码数字。

只关心业务。

```
<?php

use App\Support\Result;

public function detail(int $id): JsonResponse
{
    $coefficient = $this->service
        ->getProcessItemCoefficientDetail($id);

    return Result::success(
        '获取详情成功',
        [
            'id' => (int)$coefficient->id,
            'sort' => $coefficient->sort,
            'coefficient_name' => $coefficient->coefficient_name,
            'is_valid' => (int)$coefficient->is_valid,
            'created_by' => $coefficient->created_by,
            'updated_by' => $coefficient->updated_by,
            'created_at' => $coefficient->created_at,
            'updated_at' => $coefficient->updated_at,
        ]
    );
}
```

------

#### 1.5  Service（主动抛出异常）

例如：

```
if ($customer->balance < $amount) {

    throw new BusinessException(
        '客户余额不足'
    );
}
```

或者：

```
if ($contract->status === 1) {

    throw new BusinessException(
        '合同已审核，禁止修改'
    );
}
```

------

#### 1.6 统一异常处理

文件：

```
app/Exceptions/Handler.php
```

代码：

```
<?php

namespace App\Exceptions;

use Throwable;
use App\Support\Result;
use App\Enums\ResponseCode;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    public function render(
        $request,
        Throwable $e
    ) {

        /**
         * 参数验证异常
         */
        if ($e instanceof ValidationException) {

            return Result::error(
                ResponseCode::PARAM_ERROR,
                collect($e->errors())
                    ->flatten()
                    ->first()
            );
        }

        /**
         * 未登录
         */
        if ($e instanceof AuthenticationException) {

            return Result::error(
                ResponseCode::UNAUTHORIZED
            );
        }

        /**
         * 模型不存在
         */
        if ($e instanceof ModelNotFoundException) {

            return Result::error(
                ResponseCode::DATA_NOT_FOUND
            );
        }

        /**
         * 路由不存在
         */
        if ($e instanceof NotFoundHttpException) {

            return Result::error(
                ResponseCode::DATA_NOT_FOUND,
                '接口不存在'
            );
        }

        /**
         * 业务异常
         */
        if ($e instanceof BusinessException) {

            return Result::error(
                $e->codeEnum,
                $e->getMessage()
            );
        }

        /**
         * 数据库异常
         */
        if ($e instanceof QueryException) {

            Log::channel('exception')->error(
                '数据库异常',
                [
                    'trace_id' => $request->attributes->get('trace_id'),
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings(),
                    'message' => $e->getMessage(),
                ]
            );

            return Result::error(
                ResponseCode::DATABASE_ERROR
            );
        }

        /**
         * 系统异常日志
         */
        Log::channel('exception')->error(
            $e->getMessage(),
            [
                'trace_id' => $request->attributes->get('trace_id'),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]
        );

        /**
         * 未知异常
         */
        return Result::error(
            ResponseCode::SYSTEM_ERROR
        );
    }
}
```

### 2. 报错响应码规范

#### 2.1通用响应码

| Code | 含义         | 说明               |
| ---- | ------------ | ------------------ |
| 0    | 成功         | 接口执行成功       |
| 1    | 系统异常     | 未知异常           |
| 2    | 数据不存在   | 查询结果为空       |
| 3    | 数据已存在   | 唯一性校验失败     |
| 4    | 操作失败     | 通用失败           |
| 5    | 禁止操作     | 当前状态不允许执行 |
| 6    | 请求过于频繁 | 限流               |
| 7    | 服务暂不可用 | 系统维护           |
| 8    | 网络异常     | 第三方服务异常     |
| 9    | 并发冲突     | 乐观锁冲突         |

------

#### 2.2参数相关（10000段）

| Code  | 含义         |
| ----- | ------------ |
| 10001 | 参数错误     |
| 10002 | 必填参数缺失 |
| 10003 | 参数格式错误 |
| 10004 | 参数超出范围 |
| 10005 | 非法参数     |
| 10006 | 文件格式错误 |
| 10007 | 文件过大     |
| 10008 | 上传失败     |

示例：

```
{    "code": 10003,    "msg": "手机号格式错误",    "data": null,    "success": false}
```

------

#### 2.3认证授权（20000段）

| Code  | 含义       |
| ----- | ---------- |
| 20001 | 未登录     |
| 20002 | Token失效  |
| 20003 | Token错误  |
| 20004 | 登录已过期 |
| 20005 | 无访问权限 |
| 20006 | 账号被禁用 |
| 20007 | 账号被冻结 |
| 20008 | 密码错误   |

示例：

```
{    "code": 20001,    "msg": "请先登录",    "data": null,    "success": false}
```

------

#### 2.4数据相关（30000段）

| Code  | 含义         |
| ----- | ------------ |
| 30001 | 数据不存在   |
| 30002 | 数据已删除   |
| 30003 | 数据重复     |
| 30004 | 数据状态异常 |
| 30005 | 数据已锁定   |
| 30006 | 数据关联存在 |
| 30007 | 数据校验失败 |
| 30008 | 数据版本冲突 |

示例：

```
{    "code": 30008,    "msg": "数据已被他人修改，请刷新后重试",    "data": null,    "success": false}
```

------

#### 2.5 业务相关（40000段）

| Code  | 含义             |
| ----- | ---------------- |
| 40001 | 操作失败         |
| 40002 | 当前状态不可操作 |
| 40003 | 审批未通过       |
| 40004 | 库存不足         |
| 40005 | 金额超限         |
| 40006 | 超出配额         |
| 40007 | 已提交审核       |
| 40008 | 已完成不可修改   |
| 40009 | 重复提交         |
| 40010 | 超出业务规则限制 |

示例：

```
{    "code": 40009,    "msg": "请勿重复提交",    "data": null,    "success": false}
```

------

#### 2.6 第三方服务（50000段）

| Code  | 含义           |
| ----- | -------------- |
| 50001 | 微信接口异常   |
| 50002 | 支付宝接口异常 |
| 50003 | 短信发送失败   |
| 50004 | 邮件发送失败   |
| 50005 | OSS上传失败    |
| 50006 | Redis连接失败  |
| 50007 | MQ消息发送失败 |
| 50008 | 第三方接口超时 |

------

#### 2.7 数据库异常（60000段）

| Code  | 含义           |
| ----- | -------------- |
| 60001 | 数据库连接失败 |
| 60002 | SQL执行失败    |
| 60003 | 事务提交失败   |
| 60004 | 事务回滚       |
| 60005 | 唯一索引冲突   |
| 60006 | 外键约束失败   |
| 60007 | 死锁异常       |
| 60008 | 数据库超时     |

示例：

```
{    "code": 60005,    "msg": "数据重复，请检查后提交",    "data": null,    "success": false}
```

------

#### 2.8 系统异常（90000段）

| Code  | 含义           |
| ----- | -------------- |
| 90001 | 系统异常       |
| 90002 | 未知错误       |
| 90003 | 程序运行异常   |
| 90004 | 服务繁忙       |
| 90005 | 系统维护中     |
| 90006 | 配置错误       |
| 90007 | 文件读写失败   |
| 90008 | 服务器内部错误 |

#### 示例：

```
{    "code": 90001,    "msg": "系统异常，请联系管理员",    "data": null,    "success": false}
```