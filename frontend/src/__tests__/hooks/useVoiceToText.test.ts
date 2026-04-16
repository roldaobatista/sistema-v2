import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useVoiceToText } from '@/hooks/useVoiceToText'

describe('useVoiceToText', () => {
    let mockRecognition: any

    beforeEach(() => {
        mockRecognition = {
            continuous: false,
            interimResults: false,
            lang: '',
            maxAlternatives: 0,
            onresult: null as any,
            onerror: null as any,
            onend: null as any,
            start: vi.fn(),
            stop: vi.fn(),
        }

        ;(window as any).SpeechRecognition = vi.fn(function (this: any) {
            return mockRecognition
        })
        delete (window as any).webkitSpeechRecognition
    })

    afterEach(() => {
        vi.restoreAllMocks()
        delete (window as any).SpeechRecognition
        delete (window as any).webkitSpeechRecognition
    })

    it('should detect speech recognition support', () => {
        const { result } = renderHook(() => useVoiceToText())
        expect(result.current.isSupported).toBe(true)
    })

    it('should report unsupported when SpeechRecognition is absent', () => {
        delete (window as any).SpeechRecognition
        const { result } = renderHook(() => useVoiceToText())
        expect(result.current.isSupported).toBe(false)
    })

    it('should start with empty transcript', () => {
        const { result } = renderHook(() => useVoiceToText())
        expect(result.current.transcript).toBe('')
        expect(result.current.interimTranscript).toBe('')
        expect(result.current.isListening).toBe(false)
    })

    it('should use default language pt-BR', () => {
        const { result } = renderHook(() => useVoiceToText())

        act(() => {
            result.current.startListening()
        })

        expect(mockRecognition.lang).toBe('pt-BR')
    })

    it('should use custom language', () => {
        const { result } = renderHook(() => useVoiceToText('en-US'))

        act(() => {
            result.current.startListening()
        })

        expect(mockRecognition.lang).toBe('en-US')
    })

    it('should set isListening to true when starting', () => {
        const { result } = renderHook(() => useVoiceToText())

        act(() => {
            result.current.startListening()
        })

        expect(result.current.isListening).toBe(true)
        expect(mockRecognition.start).toHaveBeenCalled()
    })

    it('should set isListening to false when stopping', () => {
        const { result } = renderHook(() => useVoiceToText())

        act(() => {
            result.current.startListening()
        })

        act(() => {
            result.current.stopListening()
        })

        expect(result.current.isListening).toBe(false)
        expect(mockRecognition.stop).toHaveBeenCalled()
    })

    it('should handle final transcription results', () => {
        const { result } = renderHook(() => useVoiceToText())

        act(() => {
            result.current.startListening()
        })

        act(() => {
            mockRecognition.onresult({
                resultIndex: 0,
                results: [
                    { isFinal: true, 0: { transcript: 'hello world' }, length: 1 },
                ],
            })
        })

        expect(result.current.transcript).toContain('hello world')
    })

    it('should handle interim transcription results', () => {
        const { result } = renderHook(() => useVoiceToText())

        act(() => {
            result.current.startListening()
        })

        act(() => {
            mockRecognition.onresult({
                resultIndex: 0,
                results: [
                    { isFinal: false, 0: { transcript: 'hel' }, length: 1 },
                ],
            })
        })

        expect(result.current.interimTranscript).toBe('hel')
    })

    it('should set error on microphone permission denied', () => {
        const { result } = renderHook(() => useVoiceToText())

        act(() => {
            result.current.startListening()
        })

        act(() => {
            mockRecognition.onerror({ error: 'not-allowed' })
        })

        expect(result.current.error).toBe('Permissão de microfone negada')
        expect(result.current.isListening).toBe(false)
    })

    it('should set generic error for other recognition errors', () => {
        const { result } = renderHook(() => useVoiceToText())

        act(() => {
            result.current.startListening()
        })

        act(() => {
            mockRecognition.onerror({ error: 'network' })
        })

        expect(result.current.error).toBe('Erro de reconhecimento: network')
    })

    it('should not set error on aborted event', () => {
        const { result } = renderHook(() => useVoiceToText())

        act(() => {
            result.current.startListening()
        })

        act(() => {
            mockRecognition.onerror({ error: 'aborted' })
        })

        expect(result.current.error).toBeNull()
    })

    it('should clear transcript', () => {
        const { result } = renderHook(() => useVoiceToText())

        act(() => {
            result.current.appendText('some text')
        })

        act(() => {
            result.current.clearTranscript()
        })

        expect(result.current.transcript).toBe('')
        expect(result.current.interimTranscript).toBe('')
    })

    it('should append text to transcript', () => {
        const { result } = renderHook(() => useVoiceToText())

        act(() => {
            result.current.appendText('first ')
        })

        act(() => {
            result.current.appendText('second')
        })

        expect(result.current.transcript).toBe('first second')
    })

    it('should clean up recognition on unmount', () => {
        const { result, unmount } = renderHook(() => useVoiceToText())

        act(() => {
            result.current.startListening()
        })

        unmount()

        expect(mockRecognition.stop).toHaveBeenCalled()
    })

    it('should set error when trying to start without support', () => {
        delete (window as any).SpeechRecognition
        const { result } = renderHook(() => useVoiceToText())

        act(() => {
            result.current.startListening()
        })

        expect(result.current.error).toBe('Reconhecimento de voz não suportado')
    })
})
