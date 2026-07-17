// pages/QuoteComparison.js
import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { useQuery } from 'react-query';
import {
  Box,
  Typography,
  Paper,
  Grid,
  Card,
  CardContent,
  Button,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Chip,
  Radio,
  RadioGroup,
  FormControlLabel,
  LinearProgress,
  Alert,
  Divider,
} from '@mui/material';
import {
  CheckCircle,
  LocalShipping,
  AttachMoney,
  Timeline,
} from '@mui/icons-material';
import { api } from '../services/api';

export default function QuoteComparison() {
  const { requestId } = useParams();
  const [selectedQuotes, setSelectedQuotes] = useState({});
  const [bestOptions, setBestOptions] = useState({});

  const { data: quoteData, isLoading } = useQuery(
    ['quotes', requestId],
    () => api.getQuotesForRequest(requestId),
    { enabled: !!requestId }
  );

  useEffect(() => {
    if (quoteData?.items) {
      const best = {};
      quoteData.items.forEach(item => {
        if (item.quotes && item.quotes.length > 0) {
          const bestQuote = item.quotes.reduce((min, q) =>
            (q.unit_price * item.quantity + q.shipping_cost) <
            (min.unit_price * item.quantity + min.shipping_cost) ? q : min
          );
          best[item.item] = bestQuote.supplier;
          setSelectedQuotes(prev => ({ ...prev, [item.item]: bestQuote.supplier }));
        }
      });
      setBestOptions(best);
    }
  }, [quoteData]);

  if (isLoading) return <LinearProgress />;
  if (!quoteData) return <Alert severity="info">No quote request selected</Alert>;

  const calculateTotal = () => {
    let total = 0;
    quoteData.items.forEach(item => {
      const selectedQuote = item.quotes?.find(q => q.supplier === selectedQuotes[item.item]);
      if (selectedQuote) {
        total += selectedQuote.unit_price * item.quantity + selectedQuote.shipping_cost;
      }
    });
    return total;
  };

  const handleApproveQuotes = async () => {
    await api.approveQuotes({
      requestId: quoteData.request_id,
      selectedQuotes,
      totalCost: calculateTotal(),
    });
  };

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        Quote Comparison
      </Typography>
      <Typography variant="body1" color="textSecondary" paragraph>
        Request ID: {quoteData.request_id} | Created: {new Date(quoteData.created_at).toLocaleString()}
      </Typography>

      <Grid container spacing={3}>
        <Grid item xs={12}>
          <Paper sx={{ p: 3 }}>
            <Typography variant="h6" gutterBottom>
              Select Best Quotes for Each Item
            </Typography>
            {quoteData.items.map((item, idx) => (
              <Box key={idx} sx={{ mb: 4 }}>
                <Typography variant="subtitle1" gutterBottom>
                  {item.item} - Quantity: {item.quantity}
                </Typography>
                <TableContainer component={Paper} variant="outlined">
                  <Table>
                    <TableHead>
                      <TableRow>
                        <TableCell>Select</TableCell>
                        <TableCell>Supplier</TableCell>
                        <TableCell>Region</TableCell>
                        <TableCell align="right">Unit Price</TableCell>
                        <TableCell align="right">Shipping</TableCell>
                        <TableCell align="right">Total Cost</TableCell>
                        <TableCell>Lead Time</TableCell>
                        <TableCell>Best Option</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      <RadioGroup
                        value={selectedQuotes[item.item] || ''}
                        onChange={(e) => setSelectedQuotes(prev => ({
                          ...prev,
                          [item.item]: e.target.value
                        }))}
                      >
                        {item.quotes?.map((quote, qIdx) => {
                          const totalCost = quote.unit_price * item.quantity + quote.shipping_cost;
                          const isBest = bestOptions[item.item] === quote.supplier;
                          return (
                            <TableRow key={qIdx} sx={{ backgroundColor: isBest ? '#f0f9ff' : 'inherit' }}>
                              <TableCell>
                                <FormControlLabel
                                  value={quote.supplier}
                                  control={<Radio />}
                                  label=""
                                />
                              </TableCell>
                              <TableCell>{quote.supplier}</TableCell>
                              <TableCell>
                                <Chip
                                  label={quote.region}
                                  size="small"
                                  color={quote.region === 'Local' ? 'primary' : 'secondary'}
                                />
                              </TableCell>
                              <TableCell align="right">${quote.unit_price}</TableCell>
                              <TableCell align="right">${quote.shipping_cost}</TableCell>
                              <TableCell align="right">
                                <strong>${totalCost}</strong>
                              </TableCell>
                              <TableCell>{quote.lead_days} days</TableCell>
                              <TableCell>
                                {isBest && <CheckCircle color="success" />}
                              </TableCell>
                            </TableRow>
                          );
                        })}
                      </RadioGroup>
                    </TableBody>
                  </Table>
                </TableContainer>
                <Divider sx={{ my: 2 }} />
              </Box>
            ))}

            <Box sx={{ mt: 3, p: 2, bgcolor: '#f5f5f5', borderRadius: 1 }}>
              <Typography variant="h5" gutterBottom>
                Grand Total: ${calculateTotal().toLocaleString()}
              </Typography>
              <Typography variant="body2" color="textSecondary">
                Total includes item costs and shipping for all selected quotes
              </Typography>
            </Box>

            <Box sx={{ mt: 3, display: 'flex', gap: 2 }}>
              <Button
                variant="contained"
                color="primary"
                size="large"
                onClick={handleApproveQuotes}
              >
                Approve & Create Purchase Order
              </Button>
              <Button variant="outlined" size="large">
                Request New Quotes
              </Button>
            </Box>
          </Paper>
        </Grid>

        <Grid item xs={12} md={4}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                <LocalShipping /> Delivery Summary
              </Typography>
              <Typography variant="body2">
                Estimated delivery dates based on selected quotes
              </Typography>
              <Box mt={2}>
                {Object.entries(selectedQuotes).map(([item, supplier]) => {
                  const quoteItem = quoteData.items.find(i => i.item === item);
                  const quote = quoteItem?.quotes?.find(q => q.supplier === supplier);
                  return (
                    <Box key={item} sx={{ mb: 1 }}>
                      <Typography variant="body2">
                        <strong>{item}</strong>: {quote?.lead_days} days
                      </Typography>
                    </Box>
                  );
                })}
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}