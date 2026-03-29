import { QrCode, Plus, Trash2, ArrowLeft, Download } from 'lucide-react';
import { Button } from '../ui/button';
import { useState } from 'react';

interface Table {
  id: string;
  number: string;
  qrGenerated: boolean;
}

interface SetupStep4TablesProps {
  onComplete: (data: { tables: Table[]; skipped: boolean }) => void;
  onBack: () => void;
  initialData?: { tables: Table[] };
}

export function SetupStep4Tables({ onComplete, onBack, initialData }: SetupStep4TablesProps) {
  const [tables, setTables] = useState<Table[]>(initialData?.tables || []);
  const [newTableNumber, setNewTableNumber] = useState('');

  const addTable = () => {
    if (!newTableNumber.trim()) return;

    setTables([
      ...tables,
      {
        id: `table-${Date.now()}`,
        number: newTableNumber,
        qrGenerated: false
      }
    ]);
    setNewTableNumber('');
  };

  const deleteTable = (tableId: string) => {
    setTables(tables.filter(t => t.id !== tableId));
  };

  const generateQRCode = (tableId: string) => {
    setTables(tables.map(t => {
      if (t.id === tableId) {
        return { ...t, qrGenerated: true };
      }
      return t;
    }));
  };

  const generateAllQRCodes = () => {
    setTables(tables.map(t => ({ ...t, qrGenerated: true })));
  };

  const handleFinish = () => {
    onComplete({ tables, skipped: false });
  };

  const handleSkip = () => {
    onComplete({ tables: [], skipped: true });
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-3xl mx-auto px-4 py-8">
        {/* Progress */}
        <div className="mb-8">
          <p className="text-sm text-gray-500 mb-2">Activation step 4 of 4</p>
          <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
            <div className="h-full bg-emerald-600 rounded-full" style={{ width: '100%' }}></div>
          </div>
        </div>

        {/* Header */}
        <div className="mb-8">
          <div className="inline-flex items-center justify-center w-12 h-12 bg-emerald-100 rounded-lg mb-4">
            <QrCode className="w-6 h-6 text-emerald-600" />
          </div>
          <h1 className="text-3xl mb-2 text-gray-900">Tables & QR Codes</h1>
          <p className="text-gray-600 mb-2">
            Add your table numbers and generate QR codes for customer ordering.
          </p>
          <p className="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 inline-block">
            Optional – You can skip this step if you're takeaway-only
          </p>
        </div>

        {/* Add Table */}
        <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
          <h3 className="text-sm uppercase tracking-wider text-gray-500 mb-4">
            Add Table
          </h3>
          <div className="flex gap-3">
            <input
              type="text"
              value={newTableNumber}
              onChange={(e) => setNewTableNumber(e.target.value)}
              onKeyPress={(e) => e.key === 'Enter' && (e.preventDefault(), addTable())}
              placeholder="e.g., 1, A1, Patio-5"
              className="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
            <Button
              type="button"
              onClick={addTable}
              className="bg-emerald-600 hover:bg-emerald-700 text-white px-6"
            >
              <Plus className="w-4 h-4 mr-2" />
              Add Table
            </Button>
          </div>
          <p className="mt-2 text-xs text-gray-500">
            Table numbers can be any format that works for your restaurant
          </p>
        </div>

        {/* Tables List */}
        {tables.length > 0 && (
          <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-sm uppercase tracking-wider text-gray-500">
                Tables ({tables.length})
              </h3>
              <Button
                type="button"
                onClick={generateAllQRCodes}
                className="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2"
              >
                <QrCode className="w-4 h-4 mr-2" />
                Generate all QR codes
              </Button>
            </div>

            <div className="space-y-3">
              {tables.map((table) => (
                <div key={table.id} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                  <div className="flex items-center gap-4">
                    <div>
                      <p className="text-gray-900">Table {table.number}</p>
                      {table.qrGenerated && (
                        <p className="text-sm text-emerald-600">QR code ready</p>
                      )}
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    {!table.qrGenerated ? (
                      <Button
                        type="button"
                        onClick={() => generateQRCode(table.id)}
                        className="bg-gray-100 hover:bg-gray-200 text-gray-900 text-sm px-4 py-2"
                      >
                        <QrCode className="w-4 h-4 mr-2" />
                        Generate QR
                      </Button>
                    ) : (
                      <Button
                        type="button"
                        className="bg-emerald-100 hover:bg-emerald-200 text-emerald-700 text-sm px-4 py-2"
                      >
                        <Download className="w-4 h-4 mr-2" />
                        Download
                      </Button>
                    )}
                    
                    <button
                      type="button"
                      onClick={() => deleteTable(table.id)}
                      className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Empty State */}
        {tables.length === 0 && (
          <div className="bg-white rounded-xl border-2 border-dashed border-gray-300 p-12 mb-6 text-center">
            <QrCode className="w-12 h-12 text-gray-400 mx-auto mb-4" />
            <p className="text-gray-600 mb-2">No tables added yet</p>
            <p className="text-sm text-gray-500">
              Add tables to enable QR code ordering, or skip if you're takeaway-only
            </p>
          </div>
        )}

        {/* Info Box */}
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <p className="text-sm text-blue-900 mb-2">
            <strong>How QR codes work:</strong>
          </p>
          <ul className="text-sm text-blue-800 space-y-1 ml-4">
            <li className="list-disc">Each table gets a unique QR code</li>
            <li className="list-disc">Customers scan to view the menu and place orders</li>
            <li className="list-disc">Orders appear in your dashboard in real-time</li>
            <li className="list-disc">You can download and print QR codes after setup</li>
          </ul>
        </div>

        {/* Navigation */}
        <div className="flex items-center justify-between">
          <Button
            type="button"
            onClick={onBack}
            className="bg-gray-100 hover:bg-gray-200 text-gray-900 px-6 py-3"
          >
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back
          </Button>

          <div className="flex items-center gap-3">
            <button
              onClick={handleSkip}
              className="text-gray-600 hover:text-gray-900 px-4 py-3"
            >
              Skip for now
            </button>
            <Button
              onClick={handleFinish}
              className="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-3"
            >
              Finish setup
            </Button>
          </div>
        </div>

        {/* Note about skipping */}
        <p className="text-center text-sm text-gray-500 mt-4">
          You can always add tables and QR codes later from your dashboard settings
        </p>
      </div>
    </div>
  );
}