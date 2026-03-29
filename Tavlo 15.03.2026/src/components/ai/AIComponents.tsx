import { HelpCircle, Sparkles, TrendingUp, AlertCircle, CheckCircle, X } from 'lucide-react';
import { useState } from 'react';

// AI Badge - subtle indicator
export function AIBadge({ className = '' }: { className?: string }) {
  return (
    <span className={`inline-flex items-center gap-1 px-2 py-0.5 bg-purple-50 text-purple-700 rounded text-xs ${className}`}>
      <Sparkles className="w-3 h-3" />
      AI
    </span>
  );
}

// AI Tooltip - explainability
interface AITooltipProps {
  title: string;
  explanation: string;
  confidence?: number;
  dataSource?: string;
}

export function AITooltip({ title, explanation, confidence, dataSource }: AITooltipProps) {
  const [show, setShow] = useState(false);

  return (
    <div className="relative inline-block">
      <button
        onMouseEnter={() => setShow(true)}
        onMouseLeave={() => setShow(false)}
        onClick={() => setShow(!show)}
        className="p-0.5 text-gray-400 hover:text-purple-600 transition-colors"
      >
        <HelpCircle className="w-4 h-4" />
      </button>
      {show && (
        <div className="absolute z-50 bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white rounded-lg shadow-xl text-xs">
          <div className="font-medium mb-1">{title}</div>
          <div className="text-gray-300 mb-2">{explanation}</div>
          {confidence && (
            <div className="text-gray-400 text-[10px]">
              Confidence: {confidence}%
            </div>
          )}
          {dataSource && (
            <div className="text-gray-400 text-[10px] mt-1">
              {dataSource}
            </div>
          )}
          <div className="absolute top-full left-1/2 -translate-x-1/2 -mt-1">
            <div className="border-4 border-transparent border-t-gray-900"></div>
          </div>
        </div>
      )}
    </div>
  );
}

// AI Suggestion Chip - actionable
interface AISuggestionChipProps {
  label: string;
  onClick: () => void;
  icon?: React.ReactNode;
  active?: boolean;
}

export function AISuggestionChip({ label, onClick, icon, active = false }: AISuggestionChipProps) {
  return (
    <button
      onClick={onClick}
      className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm transition-all ${
        active 
          ? 'bg-purple-600 text-white shadow-md' 
          : 'bg-purple-50 hover:bg-purple-100 text-purple-700'
      }`}
    >
      {icon || <Sparkles className="w-3.5 h-3.5" />}
      {label}
    </button>
  );
}

// AI Insight Card - data-driven recommendations
interface AIInsightCardProps {
  type: 'info' | 'warning' | 'success' | 'recommendation';
  title: string;
  description: string;
  metric?: string;
  action?: {
    label: string;
    onClick: () => void;
  };
  explanation?: string;
  onDismiss?: () => void;
}

export function AIInsightCard({ 
  type, 
  title, 
  description, 
  metric, 
  action, 
  explanation,
  onDismiss 
}: AIInsightCardProps) {
  const colors = {
    info: 'bg-blue-50 border-blue-200 text-blue-900',
    warning: 'bg-orange-50 border-orange-200 text-orange-900',
    success: 'bg-green-50 border-green-200 text-green-900',
    recommendation: 'bg-purple-50 border-purple-200 text-purple-900'
  };

  const icons = {
    info: <TrendingUp className="w-5 h-5 text-blue-600" />,
    warning: <AlertCircle className="w-5 h-5 text-orange-600" />,
    success: <CheckCircle className="w-5 h-5 text-green-600" />,
    recommendation: <Sparkles className="w-5 h-5 text-purple-600" />
  };

  return (
    <div className={`relative p-4 border rounded-lg ${colors[type]}`}>
      {onDismiss && (
        <button
          onClick={onDismiss}
          className="absolute top-3 right-3 p-1 hover:bg-black/5 rounded"
        >
          <X className="w-4 h-4" />
        </button>
      )}
      <div className="flex items-start gap-3">
        {icons[type]}
        <div className="flex-1">
          <div className="flex items-center gap-2 mb-1">
            <span className="font-medium">{title}</span>
            {explanation && (
              <AITooltip 
                title="Why this suggestion?"
                explanation={explanation}
              />
            )}
          </div>
          <p className="text-sm opacity-90 mb-2">{description}</p>
          {metric && (
            <div className="text-xs font-medium opacity-75 mb-2">{metric}</div>
          )}
          {action && (
            <button
              onClick={action.onClick}
              className="text-sm font-medium underline hover:no-underline"
            >
              {action.label}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

// AI Generated Field - editable AI content
interface AIGeneratedFieldProps {
  label: string;
  value: string;
  onChange: (value: string) => void;
  onRegenerate?: () => void;
  confidence?: number;
  multiline?: boolean;
}

export function AIGeneratedField({ 
  label, 
  value, 
  onChange, 
  onRegenerate,
  confidence,
  multiline = false 
}: AIGeneratedFieldProps) {
  return (
    <div>
      <div className="flex items-center justify-between mb-2">
        <label className="text-sm font-medium flex items-center gap-2">
          {label}
          <AIBadge />
          {confidence && (
            <span className="text-xs text-gray-500">
              {confidence}% confidence
            </span>
          )}
        </label>
        {onRegenerate && (
          <button
            onClick={onRegenerate}
            className="text-xs text-purple-600 hover:text-purple-700 flex items-center gap-1"
          >
            <Sparkles className="w-3 h-3" />
            Regenerate
          </button>
        )}
      </div>
      {multiline ? (
        <textarea
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="w-full p-3 border border-purple-200 rounded-lg text-sm resize-none focus:outline-none focus:ring-2 focus:ring-purple-500"
          rows={4}
        />
      ) : (
        <input
          type="text"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="w-full px-3 py-2 border border-purple-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
        />
      )}
      <p className="text-xs text-gray-500 mt-1">You can edit this anytime</p>
    </div>
  );
}

// AI Risk Indicator
interface AIRiskIndicatorProps {
  level: 'low' | 'medium' | 'high';
  score: number;
  factors: string[];
}

export function AIRiskIndicator({ level, score, factors }: AIRiskIndicatorProps) {
  const colors = {
    low: 'bg-green-100 text-green-700 border-green-200',
    medium: 'bg-orange-100 text-orange-700 border-orange-200',
    high: 'bg-red-100 text-red-700 border-red-200'
  };

  return (
    <div className={`inline-flex items-center gap-2 px-3 py-1.5 border rounded-lg ${colors[level]}`}>
      <div className="flex items-center gap-1.5">
        <span className="text-sm font-medium">Risk: {level.toUpperCase()}</span>
        <AITooltip
          title="Risk Score Calculation"
          explanation={`Score: ${score}/100\n\nFactors:\n${factors.map(f => `• ${f}`).join('\n')}`}
          confidence={score}
          dataSource="Based on 90-day activity"
        />
      </div>
    </div>
  );
}

// AI Loading State
export function AIThinking({ message = "AI is analyzing..." }: { message?: string }) {
  return (
    <div className="flex items-center gap-2 text-sm text-purple-600">
      <Sparkles className="w-4 h-4 animate-pulse" />
      <span>{message}</span>
    </div>
  );
}

// AI Review Summary - sentiment analysis and key points
interface AIReviewSummaryProps {
  sentiment: 'positive' | 'neutral' | 'negative';
  summary: string;
  positivePoints: string[];
  negativePoints: string[];
  totalReviews: number;
  confidence: number;
}

export function AIReviewSummary({
  sentiment,
  summary,
  positivePoints,
  negativePoints,
  totalReviews,
  confidence
}: AIReviewSummaryProps) {
  const sentimentColors = {
    positive: 'bg-green-50 border-green-200',
    neutral: 'bg-gray-50 border-gray-200',
    negative: 'bg-red-50 border-red-200'
  };

  const sentimentIcons = {
    positive: '😊',
    neutral: '😐',
    negative: '😟'
  };

  return (
    <div className={`border rounded-lg p-4 ${sentimentColors[sentiment]}`}>
      <div className="flex items-start justify-between mb-3">
        <div className="flex items-center gap-2">
          <span className="text-2xl">{sentimentIcons[sentiment]}</span>
          <div>
            <div className="flex items-center gap-2">
              <span className="font-medium capitalize">{sentiment} Overall</span>
              <AIBadge />
            </div>
            <p className="text-xs text-gray-600 mt-0.5">Based on {totalReviews} reviews</p>
          </div>
        </div>
        <AITooltip
          title="How AI analyzes reviews"
          explanation="AI reads all customer reviews and identifies common themes, sentiment patterns, and key feedback points."
          confidence={Math.round(confidence * 100)}
          dataSource="Natural language processing"
        />
      </div>

      <p className="text-sm mb-3">{summary}</p>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
        {positivePoints.length > 0 && (
          <div>
            <p className="text-xs font-medium text-green-700 mb-2">👍 What customers love</p>
            <ul className="space-y-1">
              {positivePoints.map((point, idx) => (
                <li key={idx} className="text-xs text-gray-700 flex items-start gap-1.5">
                  <span className="text-green-600 mt-0.5">•</span>
                  <span>{point}</span>
                </li>
              ))}
            </ul>
          </div>
        )}

        {negativePoints.length > 0 && (
          <div>
            <p className="text-xs font-medium text-orange-700 mb-2">👎 Areas to improve</p>
            <ul className="space-y-1">
              {negativePoints.map((point, idx) => (
                <li key={idx} className="text-xs text-gray-700 flex items-start gap-1.5">
                  <span className="text-orange-600 mt-0.5">•</span>
                  <span>{point}</span>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>
    </div>
  );
}