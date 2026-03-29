import { useState } from 'react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { SUPPORTED_LANGUAGES, LanguageCode } from '@/lib/translations';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { AlertCircle } from 'lucide-react';

interface MenuTranslationsEditorProps {
  item: any;
  onChange: (translations: any) => void;
}

export function MenuTranslationsEditor({ item, onChange }: MenuTranslationsEditorProps) {
  const [activeTab, setActiveTab] = useState<LanguageCode>('en');

  // Check if translations are missing for most languages
  const hasTranslations = item.translations && Object.keys(item.translations).length > 2;

  const handleFieldChange = (language: LanguageCode, field: string, value: string) => {
    const updated = {
      ...item.translations,
      [language]: {
        ...(item.translations?.[language] || {}),
        [field]: value
      }
    };
    onChange(updated);
  };

  return (
    <div className="space-y-4">
      <div>
        <h3 className="font-semibold text-neutral-900 mb-2">Multi-Language Translations</h3>
        <p className="text-sm text-neutral-600">
          Add translations for this menu item. English is the default language and will be used as fallback.
        </p>
      </div>

      {/* Helper banner for missing translations */}
      {!hasTranslations && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
          <div className="flex items-start gap-3">
            <AlertCircle className="w-5 h-5 text-amber-600 mt-0.5 shrink-0" />
            <div className="flex-1">
              <h4 className="font-semibold text-amber-900 mb-1">No Translations Found</h4>
              <p className="text-sm text-amber-800 mb-3">
                This menu item doesn't have translations yet. To load pre-translated menu items:
              </p>
              <ol className="text-sm text-amber-800 space-y-1 mb-3 ml-4 list-decimal">
                <li>Go to <strong>Settings</strong> tab</li>
                <li>Click on <strong>Language</strong> section</li>
                <li>Click <strong>"Reset Menu with Translations"</strong> button</li>
              </ol>
              <p className="text-xs text-amber-700">
                Or manually add translations for each language using the fields below.
              </p>
            </div>
          </div>
        </div>
      )}

      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as LanguageCode)}>
        <TabsList className="grid grid-cols-6 lg:grid-cols-11 gap-2 bg-gray-100 p-2 rounded-lg h-auto">
          {SUPPORTED_LANGUAGES.map((lang) => (
            <TabsTrigger
              key={lang.code}
              value={lang.code}
              className="data-[state=active]:bg-white data-[state=active]:shadow-sm px-3 py-2 text-xs flex flex-col items-center gap-1"
            >
              <span className="text-base">{lang.flag}</span>
              <span className="uppercase">{lang.code}</span>
            </TabsTrigger>
          ))}
        </TabsList>

        {SUPPORTED_LANGUAGES.map((lang) => (
          <TabsContent key={lang.code} value={lang.code} className="space-y-4 mt-4">
            <div className="bg-gradient-to-r from-gray-50 to-gray-100 p-4 rounded-xl border border-gray-200">
              <div className="flex items-center gap-2 mb-3">
                <span className="text-2xl">{lang.flag}</span>
                <div>
                  <h4 className="font-semibold text-neutral-900">{lang.name}</h4>
                  <p className="text-xs text-neutral-600">
                    {lang.code === 'en' ? 'Default language - used as fallback' : `Translate to ${lang.name}`}
                  </p>
                </div>
              </div>

              <div className="space-y-3">
                <div>
                  <Label htmlFor={`name-${lang.code}`} className="text-sm">
                    Item Name {lang.code === 'en' && <span className="text-red-500">*</span>}
                  </Label>
                  <Input
                    id={`name-${lang.code}`}
                    value={
                      lang.code === 'en'
                        ? item.name
                        : item.translations?.[lang.code]?.name || ''
                    }
                    onChange={(e) => {
                      if (lang.code === 'en') {
                        // For English, update the main item.name field
                        const updated = { ...item, name: e.target.value };
                        onChange(item.translations || {});
                        // Notify parent about main field change
                        if ((window as any).menuTranslationMainFieldChange) {
                          (window as any).menuTranslationMainFieldChange('name', e.target.value);
                        }
                      } else {
                        handleFieldChange(lang.code, 'name', e.target.value);
                      }
                    }}
                    placeholder={`Enter name in ${lang.name}`}
                    className="mt-1.5 bg-white"
                    dir={lang.dir}
                  />
                </div>

                <div>
                  <Label htmlFor={`description-${lang.code}`} className="text-sm">
                    Description
                  </Label>
                  <Textarea
                    id={`description-${lang.code}`}
                    value={
                      lang.code === 'en'
                        ? item.description || ''
                        : item.translations?.[lang.code]?.description || ''
                    }
                    onChange={(e) => {
                      if (lang.code === 'en') {
                        if ((window as any).menuTranslationMainFieldChange) {
                          (window as any).menuTranslationMainFieldChange('description', e.target.value);
                        }
                      } else {
                        handleFieldChange(lang.code, 'description', e.target.value);
                      }
                    }}
                    placeholder={`Enter description in ${lang.name}`}
                    className="mt-1.5 bg-white resize-none"
                    rows={3}
                    dir={lang.dir}
                  />
                </div>

                {/* Add-ons Translations */}
                {item.paidAddons && item.paidAddons.length > 0 && (
                  <div>
                    <Label className="text-sm mb-2 block">Paid Add-ons</Label>
                    <div className="space-y-2">
                      {item.paidAddons.map((addon: any, index: number) => (
                        <div key={index} className="bg-white p-3 rounded-lg border border-gray-200">
                          <Input
                            value={
                              lang.code === 'en'
                                ? addon.name
                                : item.translations?.[lang.code]?.paidAddons?.[index] || ''
                            }
                            onChange={(e) => {
                              if (lang.code !== 'en') {
                                const updatedAddons = [...(item.translations?.[lang.code]?.paidAddons || [])];
                                updatedAddons[index] = e.target.value;
                                const updated = {
                                  ...item.translations,
                                  [lang.code]: {
                                    ...(item.translations?.[lang.code] || {}),
                                    paidAddons: updatedAddons
                                  }
                                };
                                onChange(updated);
                              }
                            }}
                            placeholder={`Translate "${addon.name}"`}
                            className="text-sm"
                            dir={lang.dir}
                            disabled={lang.code === 'en'}
                          />
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Free Add-ons Translations */}
                {item.freeAddons && item.freeAddons.length > 0 && (
                  <div>
                    <Label className="text-sm mb-2 block">Free Add-ons</Label>
                    <div className="space-y-2">
                      {item.freeAddons.map((addon: string, index: number) => (
                        <div key={index} className="bg-white p-3 rounded-lg border border-gray-200">
                          <Input
                            value={
                              lang.code === 'en'
                                ? addon
                                : item.translations?.[lang.code]?.freeAddons?.[index] || ''
                            }
                            onChange={(e) => {
                              if (lang.code !== 'en') {
                                const updatedAddons = [...(item.translations?.[lang.code]?.freeAddons || [])];
                                updatedAddons[index] = e.target.value;
                                const updated = {
                                  ...item.translations,
                                  [lang.code]: {
                                    ...(item.translations?.[lang.code] || {}),
                                    freeAddons: updatedAddons
                                  }
                                };
                                onChange(updated);
                              }
                            }}
                            placeholder={`Translate "${addon}"`}
                            className="text-sm"
                            dir={lang.dir}
                            disabled={lang.code === 'en'}
                          />
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Removable Items Translations */}
                {item.removableItems && item.removableItems.length > 0 && (
                  <div>
                    <Label className="text-sm mb-2 block">Removable Ingredients</Label>
                    <div className="space-y-2">
                      {item.removableItems.map((ritem: string, index: number) => (
                        <div key={index} className="bg-white p-3 rounded-lg border border-gray-200">
                          <Input
                            value={
                              lang.code === 'en'
                                ? ritem
                                : item.translations?.[lang.code]?.removableItems?.[index] || ''
                            }
                            onChange={(e) => {
                              if (lang.code !== 'en') {
                                const updatedItems = [...(item.translations?.[lang.code]?.removableItems || [])];
                                updatedItems[index] = e.target.value;
                                const updated = {
                                  ...item.translations,
                                  [lang.code]: {
                                    ...(item.translations?.[lang.code] || {}),
                                    removableItems: updatedItems
                                  }
                                };
                                onChange(updated);
                              }
                            }}
                            placeholder={`Translate "${ritem}"`}
                            className="text-sm"
                            dir={lang.dir}
                            disabled={lang.code === 'en'}
                          />
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>
          </TabsContent>
        ))}
      </Tabs>
    </div>
  );
}