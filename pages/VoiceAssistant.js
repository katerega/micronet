// pages/VoiceAssistant.js
import React, { useState, useRef } from 'react';
import {
  Box,
  Typography,
  Paper,
  Button,
  LinearProgress,
  Alert,
  Card,
  CardContent,
  List,
  ListItem,
  ListItemText,
  IconButton,
  Chip,
} from '@mui/material';
import {
  Mic,
  Stop,
  PlayArrow,
  CheckCircle,
  ShoppingCart,
} from '@mui/icons-material';
import { api } from '../services/api';

export default function VoiceAssistant() {
  const [isRecording, setIsRecording] = useState(false);
  const [transcript, setTranscript] = useState('');
  const [processing, setProcessing] = useState(false);
  const [procurementRequest, setProcurementRequest] = useState(null);
  const mediaRecorderRef = useRef(null);
  const audioChunksRef = useRef([]);

  const startRecording = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      mediaRecorderRef.current = new MediaRecorder(stream);
      audioChunksRef.current = [];

      mediaRecorderRef.current.ondataavailable = (event) => {
        audioChunksRef.current.push(event.data);
      };

      mediaRecorderRef.current.onstop = async () => {
        const audioBlob = new Blob(audioChunksRef.current, { type: 'audio/wav' });
        await processAudio(audioBlob);
      };

      mediaRecorderRef.current.start();
      setIsRecording(true);
    } catch (error) {
      console.error('Error accessing microphone:', error);
    }
  };

  const stopRecording = () => {
    if (mediaRecorderRef.current && isRecording) {
      mediaRecorderRef.current.stop();
      setIsRecording(false);
      mediaRecorderRef.current.stream.getTracks().forEach(track => track.stop());
    }
  };

  const processAudio = async (audioBlob) => {
    setProcessing(true);
    try {
      // Send audio to backend for transcription and processing
      const formData = new FormData();
      formData.append('audio', audioBlob);
      
      const response = await api.transcribeAudio(formData);
      setTranscript(response.transcript);
      
      // Extract procurement items from transcript
      const extractedItems = await api.extractProcurementItems(response.transcript);
      setProcurementRequest(extractedItems);
    } catch (error) {
      console.error('Error processing audio:', error);
    } finally {
      setProcessing(false);
    }
  };

  const handleCreateProcurement = async () => {
    if (procurementRequest) {
      await api.createProcurementFromVoice(procurementRequest);
      // Navigate to procurement requests or show success
    }
  };

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        Voice Procurement Assistant
      </Typography>
      <Typography variant="body1" color="textSecondary" paragraph>
        Speak your procurement needs and let AI create the request automatically
      </Typography>

      <Grid container spacing={3}>
        <Grid item xs={12} md={6}>
          <Paper sx={{ p: 4, textAlign: 'center' }}>
            <Box sx={{ mb: 3 }}>
              <Typography variant="h6" gutterBottom>
                {isRecording ? 'Recording...' : 'Ready to Record'}
              </Typography>
              <IconButton
                onClick={isRecording ? stopRecording : startRecording}
                sx={{
                  width: 100,
                  height: 100,
                  backgroundColor: isRecording ? '#f44336' : '#4caf50',
                  '&:hover': {
                    backgroundColor: isRecording ? '#d32f2f' : '#45a049',
                  },
                }}
              >
                {isRecording ? <Stop sx={{ fontSize: 48 }} /> : <Mic sx={{ fontSize: 48 }} />}
              </IconButton>
            </Box>

            {processing && <LinearProgress sx={{ mt: 2 }} />}

            {transcript && (
              <Card sx={{ mt: 3 }}>
                <CardContent>
                  <Typography variant="subtitle2" color="textSecondary" gutterBottom>
                    Transcription:
                  </Typography>
                  <Typography variant="body1">
                    {transcript}
                  </Typography>
                </CardContent>
              </Card>
            )}
          </Paper>
        </Grid>

        <Grid item xs={12} md={6}>
          <Paper sx={{ p: 3 }}>
            <Typography variant="h6" gutterBottom>
              Example Commands
            </Typography>
            <List>
              <ListItem>
                <ListItemText
                  primary="Order 50 router boards"
                  secondary="Creates procurement request for 50 router boards"
                />
              </ListItem>
              <ListItem>
                <ListItemText
                  primary="Need 30 fiber optic modules and 20 ethernet cables"
                  secondary="Multiple items in one request"
                />
              </ListItem>
              <ListItem>
                <ListItemText
                  primary="Urgent: Reorder 10 UPS batteries, current stock critical"
                  secondary="Adds priority flag to request"
                />
              </ListItem>
            </List>
          </Paper>
        </Grid>

        {procurementRequest && (
          <Grid item xs={12}>
            <Paper sx={{ p: 3 }}>
              <Typography variant="h6" gutterBottom>
                Extracted Procurement Items
              </Typography>
              <List>
                {procurementRequest.items.map((item, index) => (
                  <ListItem key={index}>
                    <ListItemText
                      primary={item.name}
                      secondary={`Quantity: ${item.quantity} | Priority: ${item.priority || 'Normal'}`}
                    />
                    <Chip label={item.category || 'Uncategorized'} size="small" />
                  </ListItem>
                ))}
              </List>
              <Button
                variant="contained"
                startIcon={<ShoppingCart />}
                onClick={handleCreateProcurement}
                sx={{ mt: 2 }}
              >
                Create Procurement Request
              </Button>
            </Paper>
          </Grid>
        )}
      </Grid>
    </Box>
  );
}