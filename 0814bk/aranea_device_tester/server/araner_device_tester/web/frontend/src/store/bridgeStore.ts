import { create } from 'zustand';

// 既存のブリッジストアインターフェース
export interface BridgeStore {
  // ... 既存のコード ...
  
  // Python モジュール用のストア拡張
  deviceSettings: any;
  selectedCategory: string;
  selectedDevice: string;
  setDeviceSettings: (settings: any) => void;
  setSelectedCategory: (category: string) => void;
  setSelectedDevice: (device: string) => void;
  fetchDeviceSettings: () => Promise<void>;
  saveDeviceSettings: (settings: any) => Promise<boolean>;
}

export const useBridgeStore = create<BridgeStore>((set) => ({
  // ... 既存のコード ...
  
  // Python モジュール用のストア拡張
  deviceSettings: {},
  selectedCategory: '',
  selectedDevice: '',
  setDeviceSettings: (settings) => set({ deviceSettings: settings }),
  setSelectedCategory: (category) => set({ selectedCategory: category }),
  setSelectedDevice: (device) => set({ selectedDevice: device }),
  
  // Python モジュールのデバイス設定を取得
  fetchDeviceSettings: async () => {
    // 元の PHP エンドポイントを使用
    const url = `${location.protocol}//${location.hostname.split(':')[0]}/dev/aranea_device_tester/devadmin_tool/php/settingregistrer.php?section=device_Settings`;
    console.log('[fetchDeviceSettings] request', url);
    try {
      const res = await fetch(url);
      console.log('[fetchDeviceSettings] status', res.status);
      if(!res.ok){
        console.error('[fetchDeviceSettings] HTTP error', res.status);
        return;
      }
      const json = await res.json();
      console.log('[fetchDeviceSettings] response', json);
      
      if(json.success && json.settings && Array.isArray(json.settings) && json.settings.length > 0){
        set({ deviceSettings: json.settings[0] });
      } else {
        // フォールバック: サンプルデータを使用して開発を継続
        console.warn('[fetchDeviceSettings] APIレスポンスが不正/空でした。サンプルデータを使用します');
        const sampleData = {
          "I2C LCD Device": {
            "LCD_SSD1306_128x64": {
              "GlobalSettings": {
                "displayName": "I2C_LCD_SSD1306_128x64",
                "DeviceNote": "汎用 0.66〜1.3 インチ SSD1306 128×64 OLED モジュール",
                "Interface": "[22,I2C_SCL,21,I2C_SDA]",
                "PythonModule": "oled_ssd1306_128x64.py",
                "InputFormat": "Disable",
                "OutputFormat": "string"
              },
              "ESP32Setting": {
                "width": 128,
                "height": 64,
                "i2c_address_hex": "0x3C",
                "bus_frequency_hz": 400000,
                "contrast": 255,
                "rotate": 0
              },
              "Note": "I²C アドレス 0x3D のモジュールもあり。必要に応じて書き換えること。"
            }
          }
        };
        set({ deviceSettings: sampleData });
      }
    }catch(e){
      console.error('[fetchDeviceSettings] error', e);
      
      // エラー時もサンプルデータを使用
      console.warn('[fetchDeviceSettings] フェッチエラー時のフォールバック対応: サンプルデータを使用します');
      const sampleData = {
        "I2C LCD Device": {
          "LCD_SSD1306_128x64": {
            "GlobalSettings": {
              "displayName": "I2C_LCD_SSD1306_128x64",
              "DeviceNote": "汎用 0.66〜1.3 インチ SSD1306 128×64 OLED モジュール",
              "Interface": "[22,I2C_SCL,21,I2C_SDA]",
              "PythonModule": "oled_ssd1306_128x64.py",
              "InputFormat": "Disable", 
              "OutputFormat": "string"
            },
            "ESP32Setting": {
              "width": 128,
              "height": 64,
              "i2c_address_hex": "0x3C",
              "bus_frequency_hz": 400000,
              "contrast": 255,
              "rotate": 0
            },
            "Note": "I²C アドレス 0x3D のモジュールもあり。必要に応じて書き換えること。"
          }
        }
      };
      set({ deviceSettings: sampleData });
    }
  },
  
  // Python モジュールのデバイス設定を保存
  saveDeviceSettings: async (settings) => {
    const url = `${location.protocol}//${location.hostname.split(':')[0]}/dev/aranea_device_tester/devadmin_tool/php/settingregistrer.php`;
    console.log('[saveDeviceSettings] request', url);
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `section=device_Settings&value=${encodeURIComponent(JSON.stringify([settings]))}`
      });
      
      if (!res.ok) {
        console.error('[saveDeviceSettings] HTTP error', res.status);
        return false;
      }
      
      const json = await res.json();
      console.log('[saveDeviceSettings] response', json);
      
      if (json.success) {
        set({ deviceSettings: settings });
        return true;
      } else {
        console.error('[saveDeviceSettings] API error', json);
        return false;
      }
    } catch (e) {
      console.error('[saveDeviceSettings] error', e);
      return false;
    }
  }
})); 
 
 
 
 