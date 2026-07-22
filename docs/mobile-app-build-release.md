# Sub2API 审计后台 App 构建与发布

本文记录 Capacitor App 的环境、构建、签名、发布、升级和回滚流程。Web 端与 App 共用 `frontend/` 业务代码；原生工程生成在 `frontend/android/` 和 `frontend/ios/`。

## 1. 发布标识

默认值如下，首次发布前由产品或发布负责人确认：

| 项目 | 默认值 |
| --- | --- |
| App 名称 | `Sub2API 审计后台` |
| Android applicationId | `com.sub2api.auditadmin` |
| iOS Bundle ID | `com.sub2api.auditadmin` |
| Web 目录 | `frontend/dist` |
| API 路径 | `/api/v1` |

如果需要更换 applicationId 或 Bundle ID，必须同步修改 `frontend/capacitor.config.ts`、Android 的 `applicationId`、iOS 的 Bundle Identifier，以及签名和商店配置。发布后不要随意更换，否则系统会把它识别为全新 App。

## 2. 目录与工具

```text
frontend/
  capacitor.config.ts
  android/
  ios/
  resources/              # 图标和启动屏源文件（如采用 capacitor-assets）
  dist/                   # 构建产物，不提交
```

通用工具：

- Node.js 与 pnpm，版本以项目锁文件和 CI 配置为准。
- Capacitor CLI、Android Studio、Android SDK、JDK 17。
- iOS 需要 macOS、Xcode、CocoaPods 和 Apple 开发者账号。
- 真机测试需要 Android USB 调试或 iOS 开发者签名。

签名文件、证书、Provisioning Profile、密码、CI secret 和 keystore 必须保存在仓库外的安全位置。不要把它们放入 `frontend/`、压缩包、日志或 `.env` 提交。

## 3. 固定配置

### 3.1 App API

App API 已固定为 `https://autsub2.hyojooapi.top/api/v1`，构建时不需要设置 `VITE_API_BASE_URL`。`VITE_API_PROXY_TARGET` 只用于本地 Vite 代理。App 构建前确认构建产物中没有 API Key、数据库密码、签名密码或其他服务端秘密。

### 3.2 后端 CORS

后端已固定允许正式域名、`https://localhost`、`capacitor://localhost` 和本地开发来源，不需要环境变量。配置允许 App 发送 `Authorization`、`Content-Type`、`Accept`，并通过 `OPTIONS` 预检。

修改环境变量后清理配置缓存并重启后端：

```text
php artisan config:clear
php artisan config:cache
```

至少验证以下请求：登录、`/auth/me`、401 响应、附件上传、附件下载和导出。正式环境只使用 HTTPS，并确认 API 证书链在 Android 和 iOS 真机上均可信。

## 4. 首次初始化

以下命令在 `frontend/` 目录执行。若 Capacitor 工程已存在，不重复执行 `cap init` 或 `cap add`。

```text
pnpm install --frozen-lockfile
pnpm exec cap init "Sub2API 审计后台" com.sub2api.auditadmin dist
pnpm exec cap add android
pnpm exec cap add ios
```

`cap add ios` 和后续 iOS 同步可以在 macOS 执行；Windows 只负责 Web/Android 工作流，不能生成 iOS Archive（见第 7 节）。

初始化后检查：

- `frontend/capacitor.config.ts` 的 `appId` 为 `com.sub2api.auditadmin`，`webDir` 为 `dist`。
- Android 和 iOS 的包标识一致且没有意外的旧包名。
- App 的图标、启动屏、状态栏和安全区域配置来自受控资源，不使用临时默认图标发布。

## 5. Android 构建

### 5.1 Debug 联调

```powershell
cd frontend
pnpm run app:build
pnpm exec cap sync android
.\android\gradlew.bat assembleDebug
adb install -r .\android\app\build\outputs\apk\debug\app-debug.apk
```

也可以用 Android Studio 打开 `frontend/android/`，选择 Debug 设备运行。安装后检查登录、Token 冷启动、401、系统返回键、键盘、安全区域和文件操作。

### 5.2 Release 包

Release 签名由 Android Studio 或 Gradle 的外部签名配置提供。keystore、别名和密码放在仓库外，通过 CI secret 或本机安全配置注入：

```powershell
cd frontend
pnpm run app:build
pnpm exec cap sync android
.\android\gradlew.bat assembleRelease
.\android\gradlew.bat bundleRelease
```

交付前记录 APK/AAB 的版本号、SHA-256、构建提交和签名指纹。未经签名校验的 Debug APK 不作为正式升级包。

## 6. iOS 构建

在 macOS 上执行：

```bash
cd frontend
pnpm install --frozen-lockfile
pnpm run app:build
pnpm exec cap sync ios
pnpm exec cap open ios
```

在 Xcode 中确认 Team、Bundle Identifier、Signing Certificate、Provisioning Profile、版本号和最低系统版本，然后先运行 Debug 真机，再执行 Archive。命令行示例：

```bash
xcodebuild \
  -workspace ios/App/App.xcworkspace \
  -scheme App \
  -configuration Release \
  -archivePath "$PWD/build/Sub2API-Audit.xcarchive" \
  archive
```

Windows 无法运行 Xcode，也无法生成 iOS Archive 或签名 IPA。Windows 可以完成前端构建、Capacitor 配置和 Android 包；iOS Archive 必须转移到 macOS + Xcode 环境，并在验收记录中填写执行机器和 Xcode 版本。

## 7. 发布前门禁

以下项目全部通过后才允许分发：

- `pnpm run typecheck` 和 `pnpm run app:build` 通过。
- 桌面 Web、移动 Web、App 模式关键 E2E 通过。
- Android Release APK/AAB 可安装、覆盖升级并访问正式 HTTPS API。
- iOS 真机 Debug 和 Archive/TestFlight 构建通过（发布 iOS 时）。
- 401、403、409、422、502 显示真实业务错误，不出现假成功。
- CORS 预检、登录、Token 失效、上传、下载和分享通过。
- 浅色/深色模式、320 至 430px 视口、键盘、旋转、前后台切换和系统返回键通过。
- 没有 Token、Authorization、密码或完整敏感响应进入日志和构建产物。
- 签名指纹、版本号、构建提交、产物哈希和验收记录已归档。

## 8. 版本、升级与回滚

### 8.1 版本递增

- Android 每次发布递增 `versionCode`，同时更新用户可见的 `versionName`。
- iOS 更新 `CFBundleShortVersionString` 和递增 `CFBundleVersion`。
- 前端和后端保持向后兼容；不要让 App 更新必须与数据库迁移同时上线。

### 8.2 升级检查

1. 在干净安装、覆盖安装和升级后冷启动各验证一次。
2. 确认安全 Token 迁移成功，失效 Token 会回到登录页。
3. 确认筛选、详情、财务写操作和附件能力不丢状态。
4. 保存上一稳定版本的 APK/AAB、iOS 构建记录、哈希和发布说明。

### 8.3 回滚

- 发现阻断问题时先暂停新版本分发，保留服务端 API 兼容性。
- 内部测试可重新安装上一稳定 APK；已发布到商店的版本通常不能直接降级，应发布更高版本修复或按渠道的回滚策略处理。
- iOS 通过停止 TestFlight/分阶段发布并提交修复版本处理，不把证书或 Bundle ID 改成临时值。
- 回滚后复验登录、Token、核心财务操作和文件流程，并在验收记录中注明影响版本和恢复时间。

## 9. 产物归档

每个版本至少归档：

- Android APK/AAB、SHA-256、versionCode、签名指纹。
- iOS Archive/测试分发记录、构建号和签名团队信息。
- 前端构建提交、API 地址、CORS 配置版本和环境变量清单（不含秘密值）。
- 真机验收清单、截图/录屏、已知问题和回滚说明。

签名材料和包含秘密的 CI 配置只存放在密码管理器或受控构建系统，不随产物或仓库流转。
