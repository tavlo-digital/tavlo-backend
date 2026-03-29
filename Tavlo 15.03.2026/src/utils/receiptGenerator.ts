// Austrian restaurant receipt generator

export const generateReceipt = (order: any, vendorSettings?: any) => {
  try {
    console.log('Generating receipt for order:', order);
    console.log('Vendor settings:', vendorSettings);
    
    const canvas = document.createElement('canvas');
    canvas.width = 800;
    canvas.height = 1400; // Increased height for more content
    const ctx = canvas.getContext('2d');
    
    if (!ctx) {
      console.error('Could not get canvas context');
      return;
    }
  
  // White background
  ctx.fillStyle = 'white';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  
  // Set text styles
  ctx.fillStyle = 'black';
  ctx.font = 'bold 24px Arial';
  
  let y = 50;
  
  // Restaurant header - USE VENDOR SETTINGS
  const restaurantName = vendorSettings?.restaurantName || order.restaurantName || 'La Bella Vista';
  ctx.textAlign = 'center';
  ctx.fillText(restaurantName, 400, y);
  y += 30;
  
  ctx.font = '14px Arial';
  const address = vendorSettings?.address || 'Musterstraße 123, 1010 Wien, Österreich';
  ctx.fillText(address, 400, y);
  y += 20;
  const phone = vendorSettings?.phone || '+43 1 234 5678';
  ctx.fillText(`Tel: ${phone}`, 400, y);
  y += 20;
  const uid = vendorSettings?.taxId || 'ATU12345678';
  ctx.fillText(`UID: ${uid}`, 400, y);
  y += 40;
  
  // Receipt title
  ctx.font = 'bold 20px Arial';
  ctx.fillText('RECHNUNG', 400, y);
  y += 40;
  
  // Order details
  ctx.textAlign = 'left';
  ctx.font = '14px Arial';
  ctx.fillText(`Rechnungsnummer: ${order.id || order.orderNumber}`, 50, y);
  y += 25;
  const orderDate = order.createdAt ? new Date(order.createdAt) : new Date();
  ctx.fillText(`Datum: ${orderDate.toLocaleDateString('de-AT')} ${orderDate.toLocaleTimeString('de-AT')}`, 50, y);
  y += 25;
  ctx.fillText(`Tisch: ${order.tableNumber || 'N/A'}`, 50, y);
  y += 25;
  ctx.fillText(`Personen: ${order.numPeople || 1}`, 50, y);
  y += 25;
  const paymentMethodLabel = 
    order.paymentMethod === 'apple_pay' ? 'Apple Pay' : 
    order.paymentMethod === 'google_pay' ? 'Google Pay' : 
    order.paymentMethod === 'card' ? 'Karte' : 'Bar';
  ctx.fillText(`Zahlungsart: ${paymentMethodLabel}`, 50, y);
  y += 40;
  
  // Line separator
  ctx.beginPath();
  ctx.moveTo(50, y);
  ctx.lineTo(750, y);
  ctx.stroke();
  y += 30;
  
  // Items header
  ctx.font = 'bold 14px Arial';
  ctx.fillText('Artikel', 50, y);
  ctx.textAlign = 'center';
  ctx.fillText('Menge', 450, y);
  ctx.fillText('Einzelpreis', 570, y);
  ctx.textAlign = 'right';
  ctx.fillText('Gesamt', 750, y);
  y += 25;
  
  // Line separator
  ctx.textAlign = 'left';
  ctx.beginPath();
  ctx.moveTo(50, y);
  ctx.lineTo(750, y);
  ctx.stroke();
  y += 20;
  
  // Items
  ctx.font = '14px Arial';
  let itemsGrossTotal = 0;
  
  order.items?.forEach((item: any) => {
    const itemTotal = item.price * item.quantity;
    const modifiersTotal = item.modifiers?.reduce((sum: number, m: any) => 
      sum + (m.price * item.quantity), 0) || 0;
    const totalPrice = itemTotal + modifiersTotal;
    itemsGrossTotal += totalPrice;
    
    ctx.textAlign = 'left';
    ctx.fillText(item.name, 50, y);
    ctx.textAlign = 'center';
    ctx.fillText(item.quantity.toString(), 450, y);
    ctx.fillText(`€${item.price.toFixed(2)}`, 570, y);
    ctx.textAlign = 'right';
    ctx.fillText(`€${totalPrice.toFixed(2)}`, 750, y);
    y += 20;
    
    // Modifiers
    if (item.modifiers && item.modifiers.length > 0) {
      ctx.font = '12px Arial';
      ctx.textAlign = 'left';
      item.modifiers.forEach((mod: any) => {
        ctx.fillText(`  + ${mod.name} (+€${mod.price.toFixed(2)})`, 50, y);
        y += 18;
      });
      ctx.font = '14px Arial';
    }
  });
  
  y += 20;
  
  // Line separator
  ctx.beginPath();
  ctx.moveTo(50, y);
  ctx.lineTo(750, y);
  ctx.stroke();
  y += 25;
  
  // USE ORDER'S BREAKDOWN DATA (includes service fee and correct VAT)
  const subtotal = order.subtotal || 0; // Net amount
  const serviceFee = order.serviceFee || 0; // Service fee
  const vatPercent = order.vatPercent || 20; // VAT percentage
  const vatAmount = order.vatAmount || 0; // VAT amount
  
  // Get currency symbol
  const getCurrencySymbol = () => {
    if (!vendorSettings) return '€';
    switch (vendorSettings.currency) {
      case 'EUR': return '€';
      case 'USD': return '$';
      case 'GBP': return '£';
      case 'CHF': return 'Fr.';
      default: return '€';
    }
  };
  const currency = getCurrencySymbol();
  
  // Totals breakdown
  ctx.textAlign = 'left';
  ctx.fillText('Nettobetrag (ohne MwSt.)', 50, y);
  ctx.textAlign = 'right';
  ctx.fillText(`${currency}${subtotal.toFixed(2)}`, 750, y);
  y += 25;
  
  // Show service fee if present
  if (serviceFee > 0) {
    const serviceFeePercent = ((serviceFee / subtotal) * 100).toFixed(0);
    ctx.textAlign = 'left';
    ctx.fillText(`Servicepauschale (${serviceFeePercent}%)`, 50, y);
    ctx.textAlign = 'right';
    ctx.fillText(`${currency}${serviceFee.toFixed(2)}`, 750, y);
    y += 25;
  }
  
  ctx.textAlign = 'left';
  ctx.fillText(`MwSt. ${vatPercent}% (bereits enthalten)`, 50, y);
  ctx.textAlign = 'right';
  ctx.fillText(`${currency}${vatAmount.toFixed(2)}`, 750, y);
  y += 25;
  
  ctx.textAlign = 'left';
  ctx.fillText('Zwischensumme (inkl. MwSt.)', 50, y);
  ctx.textAlign = 'right';
  ctx.fillText(`${currency}${itemsGrossTotal.toFixed(2)}`, 750, y);
  y += 25;
  
  // Loyalty discount if present
  if (order.loyaltyPointsRedeemed && order.loyaltyPointsRedeemed > 0) {
    ctx.fillStyle = '#16a34a'; // Green color
    ctx.textAlign = 'left';
    ctx.fillText(`Treuerabatt (${order.loyaltyPointsRedeemed} Punkte)`, 50, y);
    ctx.textAlign = 'right';
    ctx.fillText(`-${currency}${(order.loyaltyDiscount || 0).toFixed(2)}`, 750, y);
    ctx.fillStyle = 'black'; // Reset to black
    y += 25;
  }
  
  if (order.tip && order.tip > 0) {
    ctx.textAlign = 'left';
    ctx.fillText('Trinkgeld', 50, y);
    ctx.textAlign = 'right';
    ctx.fillText(`${currency}${order.tip.toFixed(2)}`, 750, y);
    y += 25;
  }
  
  // Final line
  ctx.strokeStyle = 'black';
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.moveTo(50, y);
  ctx.lineTo(750, y);
  ctx.stroke();
  ctx.lineWidth = 1;
  y += 30;
  
  // Total (subtract loyalty discount from items total)
  const finalTotal = itemsGrossTotal - (order.loyaltyDiscount || 0) + (order.tip || 0);
  ctx.font = 'bold 18px Arial';
  ctx.textAlign = 'left';
  ctx.fillText('GESAMT', 50, y);
  ctx.textAlign = 'right';
  ctx.fillText(`${currency}${finalTotal.toFixed(2)}`, 750, y);
  y += 50;
  
  // Footer
  ctx.font = '12px Arial';
  ctx.textAlign = 'center';
  ctx.fillText('Vielen Dank für Ihren Besuch!', 400, y);
  y += 20;
  ctx.fillText('Alle Preise verstehen sich inklusive der gesetzlichen Mehrwertsteuer.', 400, y);
  y += 30;
  
  // Tax breakdown note - USE ACTUAL VALUES
  ctx.font = '11px Arial';
  ctx.fillText('MwSt.-Aufschlüsselung gemäß UStG:', 400, y);
  y += 18;
  const footerText = serviceFee > 0 
    ? `Netto: ${currency}${subtotal.toFixed(2)} | Service: ${currency}${serviceFee.toFixed(2)} | MwSt. ${vatPercent}%: ${currency}${vatAmount.toFixed(2)} | Brutto: ${currency}${itemsGrossTotal.toFixed(2)}`
    : `Netto: ${currency}${subtotal.toFixed(2)} | MwSt. ${vatPercent}%: ${currency}${vatAmount.toFixed(2)} | Brutto: ${currency}${itemsGrossTotal.toFixed(2)}`;
  ctx.fillText(footerText, 400, y);
  
  // Convert to image and download
  canvas.toBlob((blob) => {
    if (blob) {
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `Rechnung_${order.id || order.orderNumber}_${orderDate.toISOString().split('T')[0]}.png`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
      console.log('Receipt downloaded successfully');
    } else {
      console.error('Could not create blob from canvas');
    }
  });
  } catch (error) {
    console.error('Error generating receipt:', error);
  }
};