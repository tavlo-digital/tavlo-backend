import { useState } from 'react';
import { X, Upload, Download, FileSpreadsheet, Check, AlertCircle, ArrowRight, AlertTriangle, Info } from 'lucide-react';
import { Button } from '../ui/button';
import { Card, CardContent } from '../ui/card';
import { Badge } from '../ui/badge';
import { toast } from 'sonner@2.0.3';

interface ExcelImportModalProps {
  onClose: () => void;
  onImport: (data: any[]) => void;
}

interface ImportColumn {
  excelColumn: string;
  tavloField: string | null;
  sampleValue: string;
  isRequired: boolean;
  hasWarning: boolean;
  warningMessage?: string;
}

interface ImportPreviewRow {
  ingredientName: string;
  category: string;
  unit: string;
  currentStock: number;
  reorderLevel: number;
  reorderQuantity: number;
  supplier: string;
  costPerUnit: number;
  isNew: boolean;
  isUpdate: boolean;
  hasConflict: boolean;
  conflictReason?: string;
}

export function ExcelImportModal({ onClose, onImport }: ExcelImportModalProps) {
  const [step, setStep] = useState<1 | 2 | 3>(1);
  const [file, setFile] = useState<File | null>(null);
  const [columns, setColumns] = useState<ImportColumn[]>([]);
  const [previewData, setPreviewData] = useState<ImportPreviewRow[]>([]);
  const [importing, setImporting] = useState(false);

  const tavloFields = [
    { value: null, label: '-- Skip this column --' },
    { value: 'ingredientName', label: 'Ingredient Name *', required: true },
    { value: 'category', label: 'Category', required: false },
    { value: 'unit', label: 'Unit *', required: true },
    { value: 'currentStock', label: 'Current Stock', required: false },
    { value: 'reorderLevel', label: 'Reorder Level', required: false },
    { value: 'reorderQuantity', label: 'Reorder Quantity', required: false },
    { value: 'supplier', label: 'Supplier Name', required: false },
    { value: 'costPerUnit', label: 'Cost per Unit (€)', required: false },
  ];

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = e.target.files?.[0];
    if (selectedFile) {
      const fileExt = selectedFile.name.split('.').pop()?.toLowerCase();
      if (fileExt !== 'xlsx' && fileExt !== 'csv') {
        toast.error('Please upload a .xlsx or .csv file');
        return;
      }
      setFile(selectedFile);
      parseExcelFile(selectedFile);
    }
  };

  const parseExcelFile = async (file: File) => {
    // Simulate parsing - auto-map common column names
    const mockColumns: ImportColumn[] = [
      { 
        excelColumn: 'Ingredient Name', 
        tavloField: 'ingredientName', 
        sampleValue: 'Tomatoes', 
        isRequired: true,
        hasWarning: false
      },
      { 
        excelColumn: 'Category', 
        tavloField: 'category', 
        sampleValue: 'Vegetables', 
        isRequired: false,
        hasWarning: false
      },
      { 
        excelColumn: 'Unit', 
        tavloField: 'unit', 
        sampleValue: 'kg', 
        isRequired: true,
        hasWarning: false
      },
      { 
        excelColumn: 'Current Stock', 
        tavloField: 'currentStock', 
        sampleValue: '25', 
        isRequired: false,
        hasWarning: false
      },
      { 
        excelColumn: 'Min Stock', 
        tavloField: 'reorderLevel', 
        sampleValue: '10', 
        isRequired: false,
        hasWarning: false
      },
      { 
        excelColumn: 'Reorder Quantity', 
        tavloField: 'reorderQuantity', 
        sampleValue: '20', 
        isRequired: false,
        hasWarning: false
      },
      { 
        excelColumn: 'Supplier', 
        tavloField: 'supplier', 
        sampleValue: 'Fresh Foods Co', 
        isRequired: false,
        hasWarning: false
      },
      { 
        excelColumn: 'Price', 
        tavloField: 'costPerUnit', 
        sampleValue: '€2.50', 
        isRequired: false,
        hasWarning: true,
        warningMessage: 'Contains currency symbol - will be stripped'
      },
    ];
    setColumns(mockColumns);
    setStep(2);
  };

  const handleColumnMapping = (excelColumn: string, tavloField: string | null) => {
    const updatedColumns = columns.map(col => {
      if (col.excelColumn === excelColumn) {
        // Validate the mapping
        let hasWarning = false;
        let warningMessage = '';
        
        if (tavloField === 'unit' && !['kg', 'g', 'l', 'ml', 'pcs', 'oz'].includes(col.sampleValue.toLowerCase())) {
          hasWarning = true;
          warningMessage = 'Unusual unit detected';
        }
        
        return { ...col, tavloField, hasWarning, warningMessage };
      }
      return col;
    });
    
    setColumns(updatedColumns);
  };

  const canProceedFromMapping = () => {
    const requiredFields = ['ingredientName', 'unit'];
    const mappedFields = columns.filter(col => col.tavloField).map(col => col.tavloField);
    return requiredFields.every(field => mappedFields.includes(field));
  };

  const handleProceedToReview = () => {
    if (!canProceedFromMapping()) {
      toast.error('Please map all required fields before continuing');
      return;
    }
    
    // Generate preview data
    const mockPreview: ImportPreviewRow[] = [
      {
        ingredientName: 'Tomatoes (Fresh)',
        category: 'Vegetables',
        unit: 'kg',
        currentStock: 25,
        reorderLevel: 10,
        reorderQuantity: 20,
        supplier: 'Fresh Foods Co',
        costPerUnit: 2.50,
        isNew: true,
        isUpdate: false,
        hasConflict: false
      },
      {
        ingredientName: 'Mozzarella Cheese',
        category: 'Dairy',
        unit: 'kg',
        currentStock: 15,
        reorderLevel: 10,
        reorderQuantity: 20,
        supplier: 'Italian Imports',
        costPerUnit: 8.90,
        isNew: false,
        isUpdate: true,
        hasConflict: false
      },
      {
        ingredientName: 'Olive Oil',
        category: 'Other',
        unit: 'liters',
        currentStock: 30,
        reorderLevel: 15,
        reorderQuantity: 25,
        supplier: 'Mediterranean Traders',
        costPerUnit: 12.50,
        isNew: false,
        isUpdate: false,
        hasConflict: true,
        conflictReason: 'Same name, different unit (existing: kg)'
      },
      {
        ingredientName: 'Basil',
        category: 'Spices',
        unit: 'kg',
        currentStock: 5,
        reorderLevel: 3,
        reorderQuantity: 5,
        supplier: 'Herb Garden',
        costPerUnit: 12.00,
        isNew: true,
        isUpdate: false,
        hasConflict: false
      },
      {
        ingredientName: '',
        category: '',
        unit: '',
        currentStock: 0,
        reorderLevel: 0,
        reorderQuantity: 0,
        supplier: '',
        costPerUnit: 0,
        isNew: false,
        isUpdate: false,
        hasConflict: true,
        conflictReason: 'Missing required field: Ingredient Name'
      },
    ];
    
    setPreviewData(mockPreview);
    setStep(3);
  };

  const handleImport = async () => {
    setImporting(true);
    try {
      await new Promise(resolve => setTimeout(resolve, 1500));
      
      const validRows = previewData.filter(row => !row.hasConflict);
      onImport(validRows);
      
      const result = {
        added: previewData.filter(row => row.isNew && !row.hasConflict).length,
        updated: previewData.filter(row => row.isUpdate && !row.hasConflict).length,
        skipped: previewData.filter(row => row.hasConflict).length
      };
      
      toast.success(`Import complete! ${result.added} added, ${result.updated} updated`);
      onClose();
    } catch (error) {
      toast.error('Import failed. Please try again.');
    } finally {
      setImporting(false);
    }
  };

  const downloadTemplate = () => {
    // Simple CSV template
    const headers = ['Ingredient Name', 'Category', 'Unit', 'Current Stock', 'Reorder Level', 'Reorder Quantity', 'Supplier', 'Cost per Unit'];
    const sampleRow = ['Tomatoes', 'Vegetables', 'kg', '50', '20', '50', 'Fresh Farm Co.', '2.50'];
    const csv = [headers, sampleRow].map(row => row.join(',')).join('\\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'tavlo-inventory-template.csv';
    a.click();
    toast.success('Template downloaded');
  };

  const newItems = previewData.filter(r => r.isNew && !r.hasConflict);
  const updateItems = previewData.filter(r => r.isUpdate && !r.hasConflict);
  const skippedItems = previewData.filter(r => r.hasConflict);

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-lg shadow-xl max-w-5xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        {/* Header */}
        <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
          <div>
            <h2 className="text-2xl font-bold text-gray-900">Import from Excel</h2>
            <p className="text-sm text-gray-600 mt-1">
              {step === 1 && 'Upload your ingredient list (.xlsx or .csv)'}
              {step === 2 && 'Map your columns to Tavlo fields'}
              {step === 3 && 'Review changes before importing'}
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-gray-200 rounded-lg transition-colors"
          >
            <X className="w-5 h-5 text-gray-500" />
          </button>
        </div>

        {/* Progress Steps */}
        <div className="px-6 py-4 border-b border-gray-200 bg-white">
          <div className="flex items-center gap-2">
            <div className={`flex items-center gap-2 ${step >= 1 ? 'text-orange-600' : 'text-gray-400'}`}>
              <div className={`w-8 h-8 rounded-full flex items-center justify-center font-semibold ${step >= 1 ? 'bg-orange-500 text-white' : 'bg-gray-100'}`}>
                {step > 1 ? <Check className="w-5 h-5" /> : '1'}
              </div>
              <span className="text-sm font-medium">Upload</span>
            </div>
            <div className={`flex-1 h-0.5 ${step >= 2 ? 'bg-orange-500' : 'bg-gray-300'}`} />
            <div className={`flex items-center gap-2 ${step >= 2 ? 'text-orange-600' : 'text-gray-400'}`}>
              <div className={`w-8 h-8 rounded-full flex items-center justify-center font-semibold ${step >= 2 ? 'bg-orange-500 text-white' : 'bg-gray-100'}`}>
                {step > 2 ? <Check className="w-5 h-5" /> : '2'}
              </div>
              <span className="text-sm font-medium">Map Columns</span>
            </div>
            <div className={`flex-1 h-0.5 ${step >= 3 ? 'bg-orange-500' : 'bg-gray-300'}`} />
            <div className={`flex items-center gap-2 ${step >= 3 ? 'text-orange-600' : 'text-gray-400'}`}>
              <div className={`w-8 h-8 rounded-full flex items-center justify-center font-semibold ${step >= 3 ? 'bg-orange-500 text-white' : 'bg-gray-100'}`}>
                3
              </div>
              <span className="text-sm font-medium">Review</span>
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-6">
          {/* Step 1: Upload */}
          {step === 1 && (
            <div className="space-y-6">
              {/* Reassurance Banner */}
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div className="flex gap-3">
                  <Info className="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" />
                  <div className="text-sm text-blue-900">
                    <p className="font-semibold mb-1">Nothing will change until you confirm</p>
                    <p>Review all changes in step 3 before any inventory data is updated.</p>
                  </div>
                </div>
              </div>

              <Card className="border-2 border-dashed border-gray-300 hover:border-orange-500 transition-colors">
                <CardContent className="p-12 text-center">
                  <div className="w-20 h-20 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Upload className="w-10 h-10 text-orange-600" />
                  </div>
                  <h3 className="text-lg font-semibold text-gray-900 mb-2">
                    Upload Ingredient List
                  </h3>
                  <p className="text-sm text-gray-600 mb-6 max-w-md mx-auto">
                    Supported formats: <strong>.xlsx</strong> and <strong>.csv</strong>
                  </p>
                  <input
                    type="file"
                    accept=".xlsx,.csv"
                    onChange={handleFileSelect}
                    className="hidden"
                    id="file-upload"
                  />
                  <label htmlFor="file-upload">
                    <Button asChild className="bg-orange-500 hover:bg-orange-600">
                      <span>
                        <FileSpreadsheet className="w-4 h-4 mr-2" />
                        Select File
                      </span>
                    </Button>
                  </label>
                  {file && (
                    <p className="text-sm text-gray-700 mt-4 font-medium">
                      Selected: {file.name}
                    </p>
                  )}
                </CardContent>
              </Card>

              <div className="flex items-center justify-center">
                <Button
                  onClick={downloadTemplate}
                  variant="outline"
                  className="gap-2"
                >
                  <Download className="w-4 h-4" />
                  Download Template
                </Button>
              </div>
            </div>
          )}

          {/* Step 2: Map Columns */}
          {step === 2 && (
            <div className="space-y-6">
              <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                <div className="flex gap-3">
                  <Check className="h-5 w-5 text-green-600 flex-shrink-0 mt-0.5" />
                  <div className="text-sm text-green-900">
                    <p className="font-semibold">Auto-mapping detected</p>
                    <p>We've automatically matched common column names. Review and adjust as needed.</p>
                  </div>
                </div>
              </div>

              <Card>
                <CardContent className="p-6">
                  <div className="space-y-4">
                    <div className="grid grid-cols-3 gap-4 pb-3 border-b border-gray-200 text-sm font-medium text-gray-500 uppercase">
                      <div>Your Column</div>
                      <div>Maps To</div>
                      <div>Sample Value</div>
                    </div>
                    {columns.map((col, index) => (
                      <div key={index} className="grid grid-cols-3 gap-4 items-start">
                        <div className="py-2">
                          <div className="font-medium text-gray-900">{col.excelColumn}</div>
                        </div>
                        <div>
                          <select
                            value={col.tavloField || ''}
                            onChange={(e) => handleColumnMapping(col.excelColumn, e.target.value || null)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-sm"
                          >
                            {tavloFields.map((field) => (
                              <option key={field.value || 'skip'} value={field.value || ''}>
                                {field.label}
                              </option>
                            ))}
                          </select>
                          {col.hasWarning && (
                            <div className="flex items-center gap-1 mt-1 text-xs text-amber-600">
                              <AlertTriangle className="h-3 w-3" />
                              <span>{col.warningMessage}</span>
                            </div>
                          )}
                        </div>
                        <div className="py-2">
                          <span className="text-sm text-gray-600 font-mono bg-gray-50 px-2 py-1 rounded">
                            {col.sampleValue}
                          </span>
                        </div>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>

              {!canProceedFromMapping() && (
                <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                  <div className="flex gap-3">
                    <AlertCircle className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                    <div className="text-sm text-red-900">
                      <p className="font-semibold mb-1">Missing required fields</p>
                      <p>Please map <strong>Ingredient Name</strong> and <strong>Unit</strong> to continue.</p>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Step 3: Review */}
          {step === 3 && (
            <div className="space-y-6">
              {/* Summary */}
              <div className="grid grid-cols-3 gap-4">
                <Card className="border-green-200 bg-green-50">
                  <CardContent className="p-4">
                    <div className="text-2xl font-bold text-green-700">{newItems.length}</div>
                    <div className="text-sm text-green-600">New Ingredients</div>
                  </CardContent>
                </Card>
                <Card className="border-blue-200 bg-blue-50">
                  <CardContent className="p-4">
                    <div className="text-2xl font-bold text-blue-700">{updateItems.length}</div>
                    <div className="text-sm text-blue-600">Updated Ingredients</div>
                  </CardContent>
                </Card>
                <Card className="border-amber-200 bg-amber-50">
                  <CardContent className="p-4">
                    <div className="text-2xl font-bold text-amber-700">{skippedItems.length}</div>
                    <div className="text-sm text-amber-600">Skipped Rows</div>
                  </CardContent>
                </Card>
              </div>

              {/* New Ingredients */}
              {newItems.length > 0 && (
                <div>
                  <h3 className="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <Badge className="bg-green-600 text-white">New</Badge>
                    {newItems.length} ingredient{newItems.length !== 1 ? 's' : ''} will be added
                  </h3>
                  <Card>
                    <CardContent className="p-0">
                      <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                          <thead className="bg-gray-50 border-b">
                            <tr>
                              <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                              <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                              <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                              <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                              <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-gray-200">
                            {newItems.map((item, index) => (
                              <tr key={index} className="hover:bg-gray-50">
                                <td className="px-4 py-3 font-medium text-gray-900">{item.ingredientName}</td>
                                <td className="px-4 py-3 text-gray-600">{item.category}</td>
                                <td className="px-4 py-3 text-gray-600">{item.currentStock}</td>
                                <td className="px-4 py-3 text-gray-600">{item.unit}</td>
                                <td className="px-4 py-3 text-gray-600">{item.supplier || '-'}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </CardContent>
                  </Card>
                </div>
              )}

              {/* Updated Ingredients */}
              {updateItems.length > 0 && (
                <div>
                  <h3 className="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <Badge className="bg-blue-600 text-white">Update</Badge>
                    {updateItems.length} ingredient{updateItems.length !== 1 ? 's' : ''} will be updated
                  </h3>
                  <Card>
                    <CardContent className="p-0">
                      <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                          <thead className="bg-gray-50 border-b">
                            <tr>
                              <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                              <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">New Stock</th>
                              <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-gray-200">
                            {updateItems.map((item, index) => (
                              <tr key={index} className="hover:bg-gray-50">
                                <td className="px-4 py-3 font-medium text-gray-900">{item.ingredientName}</td>
                                <td className="px-4 py-3 text-blue-700 font-medium">{item.currentStock} {item.unit}</td>
                                <td className="px-4 py-3 text-gray-600">{item.supplier || '-'}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </CardContent>
                  </Card>
                </div>
              )}

              {/* Conflicts */}
              {skippedItems.length > 0 && (
                <div>
                  <h3 className="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <Badge className="bg-amber-600 text-white">Skipped</Badge>
                    {skippedItems.length} row{skippedItems.length !== 1 ? 's' : ''} will be skipped
                  </h3>
                  <Card className="border-amber-200">
                    <CardContent className="p-4">
                      <div className="space-y-2">
                        {skippedItems.map((item, index) => (
                          <div key={index} className="flex items-start gap-3 p-3 bg-amber-50 rounded-lg">
                            <AlertTriangle className="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
                            <div className="flex-1 min-w-0">
                              <p className="font-medium text-gray-900">{item.ingredientName || 'Row ' + (index + 1)}</p>
                              <p className="text-sm text-amber-700">{item.conflictReason}</p>
                            </div>
                          </div>
                        ))}
                      </div>
                    </CardContent>
                  </Card>
                </div>
              )}

              {/* Final Confirmation */}
              <div className="bg-orange-50 border-2 border-orange-200 rounded-lg p-4">
                <div className="flex gap-3">
                  <AlertCircle className="h-5 w-5 text-orange-600 flex-shrink-0 mt-0.5" />
                  <div className="text-sm text-orange-900">
                    <p className="font-semibold mb-1">Confirm import</p>
                    <p>This will update your inventory stock levels. This action cannot be undone.</p>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50">
          <Button
            variant="outline"
            onClick={step === 1 ? onClose : () => setStep((step - 1) as 1 | 2 | 3)}
          >
            {step === 1 ? 'Cancel' : 'Back'}
          </Button>
          <div className="flex gap-2">
            {step === 1 && file && (
              <Button
                onClick={() => setStep(2)}
                className="bg-orange-500 hover:bg-orange-600"
              >
                Continue
                <ArrowRight className="w-4 h-4 ml-2" />
              </Button>
            )}
            {step === 2 && (
              <Button
                onClick={handleProceedToReview}
                disabled={!canProceedFromMapping()}
                className="bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 disabled:cursor-not-allowed"
              >
                Continue to Review
                <ArrowRight className="w-4 h-4 ml-2" />
              </Button>
            )}
            {step === 3 && (
              <Button
                onClick={handleImport}
                disabled={importing || (newItems.length === 0 && updateItems.length === 0)}
                className="bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 disabled:cursor-not-allowed"
              >
                {importing ? 'Importing...' : `Import ${newItems.length + updateItems.length} Items`}
              </Button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
