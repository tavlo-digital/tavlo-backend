import { useState } from 'react';
import { QrCode, Plus, Download, Trash2, AlertCircle } from 'lucide-react';
import { Button } from '../ui/button';

interface Table {
  id: string;
  number: string;
  qrCode: string;
  isActive: boolean;
}

interface TablesQRSetupProps {
  isVendorActive: boolean;
  initialTables?: Table[];
  onSave: (tables: Table[]) => void;
  onActivate?: () => void;
}

export function TablesQRSetup({
  isVendorActive,
  initialTables,
  onSave,
  onActivate
}: TablesQRSetupProps) {
  const [tables, setTables] = useState<Table[]>(
    initialTables || []
  );
  const [newTableNumber, setNewTableNumber] = useState('');

  const addTable = () => {
    if (!newTableNumber.trim()) return;

    const newTable: Table = {
      id: Date.now().toString(),
      number: newTableNumber,
      qrCode: `TAVLO-TABLE-${newTableNumber}-${Date.now()}`,
      isActive: isVendorActive
    };

    setTables([...tables, newTable]);
    setNewTableNumber('');
  };

  const deleteTable = (id: string) => {
    setTables(tables.filter(t => t.id !== id));
  };

  const downloadQR = (table: Table) => {
    if (!isVendorActive) {
      alert('QR codes can only be downloaded after activation');
      return;
    }
    // Simulate QR download
    console.log('Downloading QR for table:', table.number);
  };

  const downloadAllQR = () => {
    if (!isVendorActive) {
      alert('QR codes can only be downloaded after activation');
      return;
    }
    console.log('Downloading all QR codes');
  };

  const handleSave = () => {
    onSave(tables);
  };

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-5xl mx-auto px-4 sm:px-6">
        <div className="bg-white rounded-2xl shadow-sm p-8">
          {/* Header */}
          <div className="mb-8">
            <div className="flex items-center gap-3 mb-3">
              <div className="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center">
                <QrCode className="w-6 h-6 text-emerald-600" />
              </div>
              <div>
                <h1 className="text-2xl text-gray-900">Tables & QR Codes</h1>
                <p className="text-gray-600">Set up your tables and generate QR codes</p>
              </div>
            </div>
          </div>

          {/* Inactive Warning */}
          {!isVendorActive && (
            <div className="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-3">
              <AlertCircle className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
              <div className="flex-1">
                <p className="text-sm text-amber-900">
                  QR codes are currently inactive. They will be activated once you subscribe and go live.
                </p>
              </div>
            </div>
          )}

          {/* Add Table */}
          <div className="mb-8 p-6 bg-gray-50 rounded-xl border border-gray-200">
            <h3 className="text-lg text-gray-900 mb-4">Add New Table</h3>
            <div className="flex gap-3">
              <input
                type="text"
                value={newTableNumber}
                onChange={(e) => setNewTableNumber(e.target.value)}
                onKeyPress={(e) => e.key === 'Enter' && addTable()}
                className="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                placeholder="Table number (e.g., 1, A1, Patio-5)"
              />
              <Button
                onClick={addTable}
                className="bg-emerald-600 hover:bg-emerald-700 text-white px-6"
              >
                <Plus className="w-4 h-4 mr-2" />
                Add table
              </Button>
            </div>
          </div>

          {/* Tables List */}
          {tables.length === 0 ? (
            <div className="text-center py-12 text-gray-500">
              <QrCode className="w-12 h-12 mx-auto mb-3 text-gray-400" />
              <p>No tables yet. Add your first table to generate QR codes.</p>
            </div>
          ) : (
            <>
              <div className="mb-4 flex items-center justify-between">
                <h3 className="text-lg text-gray-900">Your Tables ({tables.length})</h3>
                <Button
                  onClick={downloadAllQR}
                  disabled={!isVendorActive}
                  variant="outline"
                  className="text-sm"
                >
                  <Download className="w-4 h-4 mr-2" />
                  Download all QR codes
                </Button>
              </div>

              <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                {tables.map((table) => (
                  <div
                    key={table.id}
                    className="p-5 bg-white border-2 border-gray-200 rounded-xl hover:border-emerald-300 transition-all"
                  >
                    <div className="flex items-start justify-between mb-4">
                      <div>
                        <h4 className="text-lg text-gray-900 mb-1">Table {table.number}</h4>
                        <div className="flex items-center gap-2">
                          <span className={`text-xs px-2 py-0.5 rounded-full ${
                            isVendorActive
                              ? 'bg-emerald-100 text-emerald-700'
                              : 'bg-gray-100 text-gray-600'
                          }`}>
                            {isVendorActive ? 'Active' : 'Inactive'}
                          </span>
                        </div>
                      </div>
                      <button
                        onClick={() => deleteTable(table.id)}
                        className="p-2 hover:bg-red-50 rounded-lg transition-colors"
                      >
                        <Trash2 className="w-4 h-4 text-red-600" />
                      </button>
                    </div>

                    {/* QR Code Preview */}
                    <div className={`mb-4 p-6 bg-gray-100 rounded-lg flex items-center justify-center relative ${
                      !isVendorActive ? 'opacity-50' : ''
                    }`}>
                      <QrCode className="w-16 h-16 text-gray-400" />
                      {!isVendorActive && (
                        <div className="absolute inset-0 flex items-center justify-center">
                          <span className="text-xs bg-amber-500 text-white px-2 py-1 rounded">
                            PREVIEW
                          </span>
                        </div>
                      )}
                    </div>

                    <Button
                      onClick={() => downloadQR(table)}
                      disabled={!isVendorActive}
                      variant="outline"
                      className="w-full text-sm"
                    >
                      <Download className="w-4 h-4 mr-2" />
                      Download QR
                    </Button>
                  </div>
                ))}
              </div>
            </>
          )}

          {/* Actions */}
          <div className="pt-6 border-t border-gray-200">
            {!isVendorActive && tables.length > 0 ? (
              <div className="text-center">
                <p className="text-gray-600 mb-4">
                  Ready to go live? Activate your subscription to enable QR codes.
                </p>
                <Button
                  onClick={onActivate}
                  className="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white px-8 py-3"
                >
                  Activate restaurant
                </Button>
              </div>
            ) : (
              <div className="flex items-center justify-end">
                <Button
                  onClick={handleSave}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white px-8"
                >
                  Save & continue
                </Button>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
