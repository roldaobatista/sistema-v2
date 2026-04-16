import { useState, useCallback, useRef, useEffect } from 'react'

interface SpeechRecognitionInstance extends EventTarget {
    continuous: boolean
    interimResults: boolean
    lang: string
    maxAlternatives: number
    onresult: ((event: SpeechRecognitionEvent) => void) | null
    onerror: ((event: SpeechRecognitionErrorEvent) => void) | null
    onend: (() => void) | null
    start(): void
    stop(): void
    abort(): void
}

interface SpeechRecognitionEvent extends Event {
    resultIndex: number
    results: SpeechRecognitionResultList
}

interface SpeechRecognitionErrorEvent extends Event {
    error: string
}

interface WindowWithSpeechRecognition extends Window {
    SpeechRecognition?: new () => SpeechRecognitionInstance
    webkitSpeechRecognition?: new () => SpeechRecognitionInstance
}

interface VoiceToTextState {
    isListening: boolean
    transcript: string
    interimTranscript: string
    isSupported: boolean
    error: string | null
    language: string
}

export function useVoiceToText(lang = 'pt-BR') {
    const [state, setState] = useState<VoiceToTextState>({
        isListening: false,
        transcript: '',
        interimTranscript: '',
        isSupported: typeof window !== 'undefined'
            && ('SpeechRecognition' in window || 'webkitSpeechRecognition' in window),
        error: null,
        language: lang,
    })

    const recognitionRef = useRef<SpeechRecognitionInstance | null>(null)

    const getRecognition = useCallback(() => {
        if (recognitionRef.current) return recognitionRef.current

        const win = window as unknown as WindowWithSpeechRecognition
        const SpeechRecognitionCtor = win.SpeechRecognition || win.webkitSpeechRecognition
        if (!SpeechRecognitionCtor) return null

        const recognition = new SpeechRecognitionCtor()
        recognition.continuous = true
        recognition.interimResults = true
        recognition.lang = lang
        recognition.maxAlternatives = 1

        recognition.onresult = (event: SpeechRecognitionEvent) => {
            let interim = ''
            let final = ''

            for (let i = event.resultIndex; i < event.results.length; i++) {
                const result = event.results[i]
                if (result.isFinal) {
                    final += result[0].transcript + ' '
                } else {
                    interim += result[0].transcript
                }
            }

            setState(s => ({
                ...s,
                transcript: s.transcript + final,
                interimTranscript: interim,
            }))
        }

        recognition.onerror = (event: SpeechRecognitionErrorEvent) => {
            if (event.error !== 'aborted') {
                setState(s => ({
                    ...s,
                    error: event.error === 'not-allowed'
                        ? 'Permissão de microfone negada'
                        : `Erro de reconhecimento: ${event.error}`,
                    isListening: false,
                }))
            }
        }

        recognition.onend = () => {
            setState(s => ({
                ...s,
                isListening: false,
                interimTranscript: '',
            }))
        }

        recognitionRef.current = recognition
        return recognition
    }, [lang])

    const startListening = useCallback(() => {
        const recognition = getRecognition()
        if (!recognition) {
            setState(s => ({ ...s, error: 'Reconhecimento de voz não suportado' }))
            return
        }

        setState(s => ({ ...s, error: null, isListening: true }))

        try {
            recognition.start()
        } catch {
            // Already started — ignore
        }
    }, [getRecognition])

    const stopListening = useCallback(() => {
        const recognition = recognitionRef.current
        if (recognition) {
            recognition.stop()
        }
        setState(s => ({ ...s, isListening: false }))
    }, [])

    const clearTranscript = useCallback(() => {
        setState(s => ({ ...s, transcript: '', interimTranscript: '' }))
    }, [])

    const appendText = useCallback((text: string) => {
        setState(s => ({ ...s, transcript: s.transcript + text }))
    }, [])

    useEffect(() => {
        return () => {
            if (recognitionRef.current) {
                recognitionRef.current.stop()
                recognitionRef.current = null
            }
        }
    }, [])

    return {
        ...state,
        startListening,
        stopListening,
        clearTranscript,
        appendText,
    }
}
