package cmd

import (
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"

	"admini/pkg/server"
	"github.com/spf13/cobra"
)

var (
	syslog bool
	port   int
)

var serverCmd = &cobra.Command{
	Use:     "server",
	Aliases: []string{"d", "s"},
	Short:   "Run Admini web server",
	Long:    "Run Admini web server daemon",
	Run: func(cmd *cobra.Command, args []string) {
		runServer()
	},
}

func init() {
	serverCmd.Flags().BoolVar(&syslog, "syslog", false, "Log to syslog")
	serverCmd.Flags().IntVar(&port, "port", 2222, "Port to listen on")
}

func runServer() {
	if syslog {
		log.Println("Starting Admini server with syslog enabled")
	} else {
		log.Println("Starting Admini server")
	}

	// Initialize the web server
	srv := server.NewServer()
	
	// Setup graceful shutdown
	c := make(chan os.Signal, 1)
	signal.Notify(c, os.Interrupt, syscall.SIGTERM)
	
	go func() {
		<-c
		log.Println("Shutting down Admini server...")
		os.Exit(0)
	}()

	log.Printf("Admini server starting on port %d", port)
	if err := http.ListenAndServe(fmt.Sprintf(":%d", port), srv); err != nil {
		log.Fatalf("Server failed to start: %v", err)
	}
}