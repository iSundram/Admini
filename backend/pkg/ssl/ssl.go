package ssl

import (
	"crypto/rand"
	"crypto/rsa"
	"crypto/x509"
	"crypto/x509/pkix"
	"encoding/pem"
	"fmt"
	"math/big"
	"os"
	"path/filepath"
	"time"
)

// Certificate represents an SSL certificate
type Certificate struct {
	Domain     string
	Path       string
	KeyPath    string
	CertPath   string
	ExpiryDate time.Time
	Valid      bool
}

// CreateSelfSigned creates a self-signed certificate for a domain
func CreateSelfSigned(domain, user string) error {
	// Generate private key
	privateKey, err := rsa.GenerateKey(rand.Reader, 2048)
	if err != nil {
		return fmt.Errorf("failed to generate private key: %v", err)
	}

	// Create certificate template
	template := x509.Certificate{
		SerialNumber: big.NewInt(1),
		Subject: pkix.Name{
			Organization:  []string{"DirectAdmin"},
			Country:       []string{"US"},
			Province:      []string{""},
			Locality:      []string{""},
			StreetAddress: []string{""},
			PostalCode:    []string{""},
			CommonName:    domain,
		},
		NotBefore:    time.Now(),
		NotAfter:     time.Now().Add(365 * 24 * time.Hour),
		KeyUsage:     x509.KeyUsageKeyEncipherment | x509.KeyUsageDigitalSignature,
		ExtKeyUsage:  []x509.ExtKeyUsage{x509.ExtKeyUsageServerAuth},
		IPAddresses:  []string{},
		DNSNames:     []string{domain},
	}

	// Create certificate
	certDER, err := x509.CreateCertificate(rand.Reader, &template, &template, &privateKey.PublicKey, privateKey)
	if err != nil {
		return fmt.Errorf("failed to create certificate: %v", err)
	}

	// Create SSL directory
	sslDir := filepath.Join("/usr/local/admini/data/users", user, "domains", domain)
	if err := os.MkdirAll(sslDir, 0755); err != nil {
		return fmt.Errorf("failed to create SSL directory: %v", err)
	}

	// Write private key
	keyPath := filepath.Join(sslDir, domain+".key")
	keyFile, err := os.Create(keyPath)
	if err != nil {
		return fmt.Errorf("failed to create key file: %v", err)
	}
	defer keyFile.Close()

	keyPEM := &pem.Block{
		Type:  "RSA PRIVATE KEY",
		Bytes: x509.MarshalPKCS1PrivateKey(privateKey),
	}
	if err := pem.Encode(keyFile, keyPEM); err != nil {
		return fmt.Errorf("failed to write private key: %v", err)
	}

	// Write certificate
	certPath := filepath.Join(sslDir, domain+".cert")
	certFile, err := os.Create(certPath)
	if err != nil {
		return fmt.Errorf("failed to create cert file: %v", err)
	}
	defer certFile.Close()

	certPEM := &pem.Block{
		Type:  "CERTIFICATE",
		Bytes: certDER,
	}
	if err := pem.Encode(certFile, certPEM); err != nil {
		return fmt.Errorf("failed to write certificate: %v", err)
	}

	fmt.Printf("SSL certificate created for %s\n", domain)
	return nil
}

// GetCertificate returns certificate information for a domain
func GetCertificate(domain, user string) (*Certificate, error) {
	sslDir := filepath.Join("/usr/local/admini/data/users", user, "domains", domain)
	certPath := filepath.Join(sslDir, domain+".cert")
	keyPath := filepath.Join(sslDir, domain+".key")

	cert := &Certificate{
		Domain:   domain,
		Path:     sslDir,
		KeyPath:  keyPath,
		CertPath: certPath,
		Valid:    false,
	}

	// Check if certificate files exist
	if _, err := os.Stat(certPath); os.IsNotExist(err) {
		return cert, nil
	}
	if _, err := os.Stat(keyPath); os.IsNotExist(err) {
		return cert, nil
	}

	// Read and parse certificate
	certData, err := os.ReadFile(certPath)
	if err != nil {
		return cert, err
	}

	block, _ := pem.Decode(certData)
	if block == nil {
		return cert, fmt.Errorf("failed to parse certificate PEM")
	}

	x509Cert, err := x509.ParseCertificate(block.Bytes)
	if err != nil {
		return cert, err
	}

	cert.ExpiryDate = x509Cert.NotAfter
	cert.Valid = time.Now().Before(x509Cert.NotAfter)

	return cert, nil
}

// ListCertificates returns all certificates for a user
func ListCertificates(user string) ([]Certificate, error) {
	domainsPath := filepath.Join("/usr/local/admini/data/users", user, "domains")
	entries, err := os.ReadDir(domainsPath)
	if err != nil {
		return nil, err
	}

	var certificates []Certificate
	for _, entry := range entries {
		if entry.IsDir() {
			cert, err := GetCertificate(entry.Name(), user)
			if err != nil {
				continue
			}
			if cert.Valid {
				certificates = append(certificates, *cert)
			}
		}
	}

	return certificates, nil
}