import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'com.sub2api.auditadmin',
  appName: 'Sub2API 审计后台',
  webDir: 'dist',
  server: {
    hostname: 'localhost',
    androidScheme: 'https',
    iosScheme: 'capacitor',
  },
  plugins: {
    App: {
      disableBackButtonHandler: true,
    },
    Keyboard: {
      resize: 'native',
      resizeOnFullScreen: true,
      autoBackdropColor: 'dom',
    },
    SplashScreen: {
      launchAutoHide: true,
      launchShowDuration: 500,
      launchFadeOutDuration: 180,
      backgroundColor: '#f7f9fc',
      showSpinner: false,
    },
    StatusBar: {
      overlaysWebView: false,
      style: 'DARK',
      backgroundColor: '#f7f9fc',
    },
  },
}

export default config
